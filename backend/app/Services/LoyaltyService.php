<?php

namespace App\Services;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Support\TenantContext;

class LoyaltyService
{
    /** 1 point per euro spent (rounded down), awarded on delivery. */
    public function awardForDeliveredOrder(Order $order): void
    {
        $order->loadMissing('tenant');
        $tenant = $order->tenant ?? TenantContext::get();

        if ($tenant && ! $tenant->loyaltyEnabled()) {
            return;
        }

        if (! $order->customer_session || $order->loyalty_points_earned > 0) {
            return;
        }

        $points = (int) floor((float) $order->total);

        if ($points <= 0) {
            return;
        }

        $account = LoyaltyAccount::query()->firstOrCreate(
            [
                'tenant_id' => $order->tenant_id,
                'customer_session' => $order->customer_session,
            ],
            [
                'customer_name' => $order->customer_name,
                'points' => 0,
            ]
        );

        if ($order->customer_name && ! $account->customer_name) {
            $account->customer_name = $order->customer_name;
        }

        $account->points += $points;
        $account->save();

        LoyaltyTransaction::create([
            'tenant_id' => $order->tenant_id,
            'loyalty_account_id' => $account->id,
            'order_id' => $order->id,
            'points' => $points,
            'reason' => 'order_delivered',
        ]);

        $order->loyalty_points_earned = $points;
        $order->save();
    }

    public function balanceForSession(string $session): ?LoyaltyAccount
    {
        return LoyaltyAccount::query()
            ->where('customer_session', $session)
            ->when(TenantContext::id(), fn ($q) => $q->where('tenant_id', TenantContext::id()))
            ->first();
    }
}
