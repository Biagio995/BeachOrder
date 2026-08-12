<?php

namespace App\Services\Pos;

use App\Jobs\SyncOrderToPosJob;
use App\Models\Order;
use App\Models\PosIntegration;
use App\Models\PosOrderSync;
use App\Models\PosSyncAttempt;
use App\Support\LogRedactor;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderSyncService
{
    public function __construct(
        private readonly PosAdapterRegistry $registry,
        private readonly PosOrderPayloadBuilder $payloadBuilder,
    ) {}

    public function queueForOrder(Order $order): ?PosOrderSync
    {
        $integration = PosIntegration::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('is_enabled', true)
            ->first();

        if (! $integration?->isOperational()) {
            return null;
        }

        $sync = $this->createOrGetSync($order, $integration);

        if ($sync->sync_status === 'synced') {
            return $sync;
        }

        if (in_array($sync->sync_status, ['pending', 'retrying'], true)) {
            SyncOrderToPosJob::dispatch($sync->id);
        }

        return $sync;
    }

    public function createOrGetSync(Order $order, PosIntegration $integration): PosOrderSync
    {
        $idempotencyKey = $this->idempotencyKey($order);

        return PosOrderSync::query()->firstOrCreate(
            [
                'order_id' => $order->id,
            ],
            [
                'tenant_id' => $order->tenant_id,
                'pos_integration_id' => $integration->id,
                'idempotency_key' => $idempotencyKey,
                'sync_status' => 'pending',
                'max_retries' => (int) config('pos.max_retries', 5),
            ]
        );
    }

    public function processSync(PosOrderSync $sync): PosOrderSync
    {
        return DB::transaction(function () use ($sync) {
            $sync = PosOrderSync::query()->lockForUpdate()->findOrFail($sync->id);

            if ($sync->sync_status === 'synced') {
                return $sync;
            }

            $integration = $sync->integration;
            if (! $integration?->is_enabled) {
                return $this->markFailed($sync, 'POS integration is disabled.');
            }

            $order = $sync->order()->with(['items', 'location', 'tenant'])->first();
            if (! $order) {
                return $this->markFailed($sync, 'Order not found.');
            }

            TenantContext::set($order->tenant);

            $attemptNumber = $sync->retry_count + 1;
            $sync->sync_status = 'processing';
            $sync->save();

            PosSyncAttempt::query()->create([
                'pos_order_sync_id' => $sync->id,
                'attempt_number' => $attemptNumber,
                'status' => 'processing',
                'attempted_at' => now(),
            ]);

            try {
                $payload = $this->payloadBuilder->build($order, $integration, $sync->idempotency_key);
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?? 'Mapping validation failed.';

                return $this->markFailed($sync, $message, retryable: false, attemptNumber: $attemptNumber);
            }

            $adapter = $this->registry->for($integration);
            $result = $adapter->createOrder($integration, $order, $payload);

            if ($result->success && $result->externalOrderId) {
                return $this->markSynced($sync, $result->externalOrderId, $result->message, $result->metadata, $attemptNumber);
            }

            return $this->handleFailure($sync, $result->message ?? 'POS synchronization failed.', $result->metadata, $attemptNumber);
        });
    }

    public function retryManually(PosOrderSync $sync): PosOrderSync
    {
        if ($sync->sync_status === 'synced') {
            throw ValidationException::withMessages([
                'sync' => ['Order is already synchronized.'],
            ]);
        }

        $sync->sync_status = 'retrying';
        $sync->next_retry_at = now();
        $sync->save();

        SyncOrderToPosJob::dispatch($sync->id);

        return $sync->fresh();
    }

    public function scheduleRetries(): int
    {
        $due = PosOrderSync::query()
            ->whereIn('sync_status', ['failed', 'retrying'])
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit(100)
            ->get();

        foreach ($due as $sync) {
            $sync->sync_status = 'retrying';
            $sync->save();
            SyncOrderToPosJob::dispatch($sync->id);
        }

        return $due->count();
    }

    public function idempotencyKey(Order $order): string
    {
        return 'tenant:'.$order->tenant_id.':order:'.$order->id;
    }

    private function markSynced(
        PosOrderSync $sync,
        string $externalOrderId,
        ?string $message,
        array $metadata,
        int $attemptNumber,
    ): PosOrderSync {
        $sync->external_order_id = $externalOrderId;
        $sync->sync_status = 'synced';
        $sync->last_error = null;
        $sync->synced_at = now();
        $sync->next_retry_at = null;
        $sync->metadata = array_merge($sync->metadata ?? [], $metadata);
        $sync->save();

        $integration = $sync->integration;
        if ($integration) {
            $integration->last_sync_at = now();
            $integration->save();
        }

        $this->updateAttempt($sync, $attemptNumber, 'success', $message, $metadata);

        return $sync->fresh();
    }

    private function handleFailure(
        PosOrderSync $sync,
        string $message,
        array $metadata,
        int $attemptNumber,
    ): PosOrderSync {
        $retryable = $sync->retry_count + 1 < $sync->max_retries;

        return $this->markFailed($sync, $message, $retryable, $metadata, $attemptNumber);
    }

    private function markFailed(
        PosOrderSync $sync,
        string $message,
        bool $retryable = true,
        array $metadata = [],
        ?int $attemptNumber = null,
    ): PosOrderSync {
        $sanitized = $this->sanitize($message);
        $sync->retry_count = $sync->retry_count + 1;
        $sync->last_error = $sanitized;
        $sync->metadata = array_merge($sync->metadata ?? [], $metadata);

        if ($retryable && $sync->retry_count < $sync->max_retries) {
            $sync->sync_status = 'retrying';
            $sync->next_retry_at = now()->addSeconds($this->backoffSeconds($sync->retry_count));
            $sync->save();

            SyncOrderToPosJob::dispatch($sync->id)->delay($sync->next_retry_at);

            if ($attemptNumber !== null) {
                $this->updateAttempt($sync, $attemptNumber, 'failed', $sanitized, $metadata);
            }

            return $sync->fresh();
        }

        $sync->sync_status = 'failed';
        $sync->next_retry_at = null;
        $sync->save();

        if ($attemptNumber !== null) {
            $this->updateAttempt($sync, $attemptNumber, 'failed', $sanitized, $metadata);
        }

        return $sync->fresh();
    }

    private function backoffSeconds(int $retryCount): int
    {
        $base = max(1, (int) config('pos.retry_base_seconds', 30));

        return min($base * (2 ** max(0, $retryCount - 1)), 3600);
    }

    private function updateAttempt(
        PosOrderSync $sync,
        int $attemptNumber,
        string $status,
        ?string $message,
        array $metadata,
    ): void {
        PosSyncAttempt::query()
            ->where('pos_order_sync_id', $sync->id)
            ->where('attempt_number', $attemptNumber)
            ->update([
                'status' => $status,
                'error_message' => $this->sanitize($message),
                'response_summary' => LogRedactor::redact($metadata),
            ]);
    }

    private function sanitize(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        return (string) LogRedactor::redact($message);
    }
}
