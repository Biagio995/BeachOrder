<?php

namespace App\Http\Controllers\Webhook;

use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosIntegration;
use App\Models\PosOrderSync;
use App\Models\Tenant;
use App\Support\LogRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PosWebhookController extends Controller
{
    public function __invoke(Request $request, string $tenantSlug): Response
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if (! $tenant) {
            return response('Not found', 404);
        }

        $integration = PosIntegration::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_enabled', true)
            ->first();

        if (! $integration?->webhook_secret) {
            return response('Integration not configured', 404);
        }

        $signature = $request->header('X-POS-Signature')
            ?? $request->header('X-Webhook-Signature');

        if (! $this->verifySignature($request->getContent(), (string) $signature, $integration->webhook_secret)) {
            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? $payload['type'] ?? '');
        $externalOrderId = (string) ($payload['external_order_id'] ?? $payload['order_id'] ?? '');

        if ($externalOrderId === '') {
            return response('Missing order reference', 422);
        }

        $sync = PosOrderSync::query()
            ->where('tenant_id', $tenant->id)
            ->where('external_order_id', $externalOrderId)
            ->first();

        if (! $sync) {
            Log::info('pos.webhook.unknown_order', LogRedactor::redact([
                'tenant_id' => $tenant->id,
                'event' => $event,
                'external_order_id' => $externalOrderId,
            ]));

            return response('OK', 200);
        }

        $order = $sync->order;
        if (! $order) {
            return response('OK', 200);
        }

        $this->handleEvent($order, $event, $payload);

        return response('OK', 200);
    }

    private function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleEvent(Order $order, string $event, array $payload): void
    {
        $normalized = strtolower($event);

        if (in_array($normalized, ['order.cancelled', 'order.canceled', 'cancelled'], true)) {
            if ($order->status !== 'cancelled') {
                $order->status = 'cancelled';
                $order->cancelled_at = now();
                $order->save();
                event(new OrderUpdated($order));
            }

            return;
        }

        $statusMap = [
            'order.accepted' => 'accepted',
            'order.preparing' => 'preparing',
            'order.ready' => 'ready',
            'order.delivered' => 'delivered',
            'accepted' => 'accepted',
            'preparing' => 'preparing',
            'ready' => 'ready',
            'delivered' => 'delivered',
        ];

        $mapped = $statusMap[$normalized] ?? ($payload['status'] ?? null);
        if (! is_string($mapped) || ! $order->canTransitionTo($mapped)) {
            return;
        }

        $order->status = $mapped;
        match ($mapped) {
            'ready' => $order->ready_at = $order->ready_at ?? now(),
            'delivered' => $order->delivered_at = $order->delivered_at ?? now(),
            default => null,
        };
        $order->save();

        try {
            event(new OrderUpdated($order));
        } catch (\Throwable) {
            //
        }
    }
}
