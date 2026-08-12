<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookMonitor
{
    public static function recordSuccess(string $provider, string $eventType): void
    {
        Cache::put((string) config('monitoring.webhook_last_success_key'), [
            'provider' => $provider,
            'event_type' => $eventType,
            'at' => now()->toIso8601String(),
        ], now()->addDays(30));

        Log::channel('payments')->info('payment.webhook.success', [
            'provider' => $provider,
            'event_type' => $eventType,
        ]);
    }

    public static function recordFailure(string $provider, string $eventType, string $reason): void
    {
        Cache::put((string) config('monitoring.webhook_last_failure_key'), [
            'provider' => $provider,
            'event_type' => $eventType,
            'reason' => $reason,
            'at' => now()->toIso8601String(),
        ], now()->addDays(30));

        Log::channel('payments')->error('payment.webhook.failure', [
            'provider' => $provider,
            'event_type' => $eventType,
            'reason' => $reason,
        ]);

        CriticalErrorAlerter::alert('payment_webhook_failure', 'Payment webhook processing failed', [
            'provider' => $provider,
            'event_type' => $eventType,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{status: string, last_success_at: ?string, last_failure_at: ?string, provider: ?string}
     */
    public static function health(): array
    {
        $lastSuccess = Cache::get((string) config('monitoring.webhook_last_success_key'));
        $lastFailure = Cache::get((string) config('monitoring.webhook_last_failure_key'));
        $staleAfter = (int) config('monitoring.webhook_stale_after_seconds', 86400);

        $lastSuccessAt = is_array($lastSuccess) ? ($lastSuccess['at'] ?? null) : null;

        if ($lastSuccessAt === null) {
            return [
                'status' => 'unknown',
                'last_success_at' => null,
                'last_failure_at' => is_array($lastFailure) ? ($lastFailure['at'] ?? null) : null,
                'provider' => null,
            ];
        }

        $age = now()->diffInSeconds($lastSuccessAt);
        $status = $age <= $staleAfter ? 'ok' : 'stale';

        return [
            'status' => $status,
            'last_success_at' => $lastSuccessAt,
            'last_failure_at' => is_array($lastFailure) ? ($lastFailure['at'] ?? null) : null,
            'provider' => is_array($lastSuccess) ? ($lastSuccess['provider'] ?? null) : null,
        ];
    }
}
