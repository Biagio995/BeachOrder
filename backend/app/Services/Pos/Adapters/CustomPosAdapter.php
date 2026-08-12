<?php

namespace App\Services\Pos\Adapters;

use App\Models\PosIntegration;

class CustomPosAdapter extends AbstractHttpPosAdapter
{
    public function provider(): string
    {
        return 'custom';
    }

    protected function providerLabel(): string
    {
        return 'Custom POS';
    }

    protected function authHeaders(PosIntegration $integration): array
    {
        $credentials = $integration->credentials ?? [];
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (filled($credentials['api_key'] ?? null)) {
            $headers['Authorization'] = 'Bearer '.$credentials['api_key'];
        }

        if (filled($credentials['username'] ?? null) && filled($credentials['password'] ?? null)) {
            $headers['Authorization'] = 'Basic '.base64_encode($credentials['username'].':'.$credentials['password']);
        }

        foreach (($integration->extra_params['auth_headers'] ?? []) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $headers[$key] = (string) $value;
            }
        }

        return $headers;
    }

    protected function ordersPath(PosIntegration $integration): string
    {
        return (string) ($integration->extra_params['orders_path'] ?? '/orders');
    }
}
