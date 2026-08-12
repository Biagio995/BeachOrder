<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationAccessToken;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationAccessService
{
    /** Hours a claimed scan remains usable before an order. */
    public const TTL_HOURS = 4;

    public function claim(Location $location, ?string $customerSession = null): LocationAccessToken
    {
        if ($customerSession) {
            LocationAccessToken::query()
                ->where('location_id', $location->id)
                ->where('customer_session', $customerSession)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['consumed_at' => now()]);
        }

        return LocationAccessToken::create([
            'location_id' => $location->id,
            'token' => (string) Str::uuid(),
            'customer_session' => $customerSession,
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);
    }

    public function assertValid(
        string $token,
        Location $location,
        ?string $customerSession = null,
        bool $lock = false,
    ): LocationAccessToken {
        $query = LocationAccessToken::query()
            ->where('token', $token)
            ->where('location_id', $location->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $access = $query->first();

        if (! $access || ! $access->isValid()) {
            throw ValidationException::withMessages([
                'access_token' => ['Access expired. Scan the QR code again to order.'],
            ]);
        }

        if ($customerSession && $access->customer_session && ! hash_equals($access->customer_session, $customerSession)) {
            throw ValidationException::withMessages([
                'access_token' => ['Access token does not match this session.'],
            ]);
        }

        return $access;
    }

    public function consume(LocationAccessToken $access, Order $order): void
    {
        $access->consumed_at = now();
        $access->order_id = $order->id;
        $access->save();
    }
}
