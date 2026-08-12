<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationAccessController extends Controller
{
    public function __construct(private LocationAccessService $access) {}

    /**
     * Mint a one-order access token from a stable QR location code.
     * The printed QR never changes; each scan claims a fresh token.
     */
    public function claim(Request $request, string $tenant, string $code): JsonResponse
    {
        $data = $request->validate([
            'customer_session' => ['nullable', 'uuid'],
        ]);

        $location = Location::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        $token = $this->access->claim($location, $data['customer_session'] ?? null);

        return response()->json([
            'access_token' => $token->token,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'location' => $location,
        ]);
    }
}
