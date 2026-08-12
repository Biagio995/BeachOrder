<?php

namespace App\Services\Pos\Adapters;

use App\Models\PosIntegration;

class SoftOneAdapter extends AbstractHttpPosAdapter
{
    public function provider(): string
    {
        return 'softone';
    }

    protected function providerLabel(): string
    {
        return 'SoftOne/EntersoftONE';
    }

    protected function authHeaders(PosIntegration $integration): array
    {
        $credentials = $integration->credentials ?? [];

        return array_filter([
            'Authorization' => filled($credentials['api_key'] ?? null)
                ? 'Bearer '.$credentials['api_key']
                : null,
            'X-Company-Id' => $integration->store_location_id,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    protected function ordersPath(PosIntegration $integration): string
    {
        return '/api/v1/orders';
    }
}
