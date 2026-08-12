<?php

namespace App\Services\Pos\Adapters;

use App\Models\PosIntegration;

class EpsilonPylonAdapter extends AbstractHttpPosAdapter
{
    public function provider(): string
    {
        return 'epsilon_pylon';
    }

    protected function providerLabel(): string
    {
        return 'Epsilon/PYLON';
    }

    protected function authHeaders(PosIntegration $integration): array
    {
        $credentials = $integration->credentials ?? [];

        return array_filter([
            'Authorization' => filled($credentials['api_key'] ?? null)
                ? 'Bearer '.$credentials['api_key']
                : null,
            'X-Store-Id' => $integration->store_location_id,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    protected function ordersPath(PosIntegration $integration): string
    {
        return '/orders';
    }
}
