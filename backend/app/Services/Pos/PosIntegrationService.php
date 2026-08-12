<?php

namespace App\Services\Pos;

use App\Models\PosIntegration;
use App\Support\LogRedactor;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosIntegrationService
{
    public function __construct(
        private readonly PosAdapterRegistry $registry,
    ) {}

    public function getOrCreateForTenant(): PosIntegration
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context missing.']]);
        }

        return PosIntegration::query()
            ->with('tenant')
            ->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'provider' => 'epsilon_pylon',
                'connection_status' => 'disconnected',
                'is_enabled' => false,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PosIntegration $integration, array $data): PosIntegration
    {
        if (array_key_exists('credentials', $data)) {
            $incoming = is_array($data['credentials']) ? $data['credentials'] : [];
            $current = $integration->credentials ?? [];
            $merged = array_replace($current, array_filter(
                $incoming,
                fn ($value) => $value !== null && $value !== ''
            ));

            if (isset($incoming['api_key']) && $incoming['api_key'] === '') {
                unset($merged['api_key']);
            }
            if (isset($incoming['password']) && $incoming['password'] === '') {
                unset($merged['password']);
            }

            $data['credentials'] = $merged !== [] ? $merged : null;
        }

        if (($data['is_enabled'] ?? false) && ! ($data['provider'] ?? $integration->provider)) {
            throw ValidationException::withMessages([
                'provider' => ['Select a POS provider before enabling integration.'],
            ]);
        }

        $integration->fill($data);
        $integration->save();

        return $integration->fresh();
    }

    public function testConnection(PosIntegration $integration): PosIntegration
    {
        $adapter = $this->registry->for($integration);
        $result = $adapter->testConnection($integration);

        $integration->last_connection_test_at = now();
        $integration->connection_status = $result->success ? 'connected' : 'error';
        $integration->last_connection_error = $result->success
            ? null
            : $this->sanitize($result->message);
        $integration->save();

        return $integration->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(PosIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'provider' => $integration->provider,
            'api_endpoint' => $integration->api_endpoint,
            'store_location_id' => $integration->store_location_id,
            'extra_params' => $integration->extra_params ?? [],
            'is_enabled' => $integration->is_enabled,
            'connection_status' => $integration->connection_status,
            'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
            'last_connection_test_at' => $integration->last_connection_test_at?->toIso8601String(),
            'last_connection_error' => $integration->last_connection_error,
            'has_credentials' => filled($integration->credentials),
            'credentials' => $this->maskedCredentials($integration),
            'webhook_url' => $this->webhookUrl($integration),
            'providers' => PosIntegration::PROVIDERS,
        ];
    }

    public function ensureWebhookSecret(PosIntegration $integration): PosIntegration
    {
        if (! $integration->webhook_secret) {
            $integration->webhook_secret = Str::random(40);
            $integration->save();
        }

        return $integration;
    }

    public function webhookUrl(PosIntegration $integration): string
    {
        $tenant = $integration->tenant;
        $base = rtrim((string) config('app.url'), '/');

        return $base.'/api/webhooks/pos/'.$tenant->slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function maskedCredentials(PosIntegration $integration): array
    {
        $credentials = $integration->credentials ?? [];
        $masked = [];

        foreach ($credentials as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $string = (string) $value;
            if (in_array($key, ['api_key', 'password', 'secret', 'token'], true)) {
                $masked[$key] = $string !== '' ? '••••••••' : '';
            } else {
                $masked[$key] = $string;
            }
        }

        if (isset($credentials['stub_mode'])) {
            $masked['stub_mode'] = (bool) $credentials['stub_mode'];
        }

        return $masked;
    }

    private function sanitize(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        return (string) LogRedactor::redact($message);
    }
}
