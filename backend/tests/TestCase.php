<?php

namespace Tests;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function activateTenantSubscription(Tenant $tenant, string $status = Subscription::STATUS_ACTIVE): Subscription
    {
        $subscription = Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'status' => $status,
                'plan' => Subscription::PLAN_ANNUAL,
                'price_cents' => 29900,
                'currency' => 'EUR',
                'started_at' => now()->subMonth(),
                'current_period_start' => now()->subMonth(),
                'current_period_end' => now()->addYear(),
            ]
        );

        if ($status === Subscription::STATUS_ACTIVE || $status === Subscription::STATUS_PAST_DUE) {
            $tenant->forceFill(['is_active' => true])->save();
        }

        return $subscription;
    }
}
