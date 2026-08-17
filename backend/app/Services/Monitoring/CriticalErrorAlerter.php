<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CriticalErrorAlerter
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function alert(string $key, string $message, array $context = []): void
    {
        Log::channel('monitoring')->critical($message, array_merge(['alert_key' => $key], $context));

        $webhook = config('monitoring.alert_slack_webhook');
        if (! is_string($webhook) || $webhook === '') {
            return;
        }

        $cooldown = (int) config('monitoring.alert_cooldown_seconds', 300);
        $cacheKey = 'monitoring:alert:cooldown:'.$key;

        if (! Cache::add($cacheKey, true, now()->addSeconds($cooldown))) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, [
                'text' => sprintf('*[Servio]* %s', $message),
                'attachments' => [[
                    'color' => 'danger',
                    'fields' => collect($context)->map(fn ($value, $field) => [
                        'title' => (string) $field,
                        'value' => is_scalar($value) ? (string) $value : json_encode($value),
                        'short' => true,
                    ])->values()->all(),
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::channel('monitoring')->warning('alert.delivery_failed', [
                'alert_key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $health
     */
    public static function alertIfUnhealthy(array $health): void
    {
        if (($health['status'] ?? '') !== 'unhealthy') {
            return;
        }

        CriticalErrorAlerter::alert('health_unhealthy', 'Application health check is UNHEALTHY', [
            'checks' => json_encode($health['checks'] ?? []),
        ]);
    }
}
