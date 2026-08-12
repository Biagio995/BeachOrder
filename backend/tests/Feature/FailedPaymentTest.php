<?php

namespace Tests\Feature;

use App\Jobs\SuspendPastDueSubscriptionsJob;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionReactivatedNotification;
use App\Notifications\SubscriptionSuspendedNotification;
use App\Services\SubscriptionBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FailedPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_payment_sets_past_due_and_notifies_admin(): void
    {
        Notification::fake();
        config(['billing.grace_period_days' => 7]);

        $tenant = $this->createTenantWithAdmin();
        $subscription = $this->activateTenantSubscription($tenant);

        app(SubscriptionBillingService::class)->handlePaymentFailed($subscription);

        $subscription->refresh();
        $tenant->refresh();

        $this->assertSame(Subscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertNotNull($subscription->past_due_at);
        $this->assertNotNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->grantsPlatformAccess());
        $this->assertTrue($tenant->is_active);

        Notification::assertSentTo(
            User::query()->where('email', 'admin@test.com')->first(),
            PaymentFailedNotification::class,
        );
    }

    public function test_grace_period_expiry_suspends_tenant_without_deleting_data(): void
    {
        Notification::fake();

        $tenant = $this->createTenantWithAdmin();
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => Subscription::STATUS_PAST_DUE,
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => 29900,
            'currency' => 'EUR',
            'past_due_at' => now()->subDays(10),
            'grace_period_ends_at' => now()->subDay(),
        ]);

        app(SubscriptionBillingService::class)->suspendExpiredGracePeriods();

        $subscription->refresh();
        $tenant->refresh();

        $this->assertSame(Subscription::STATUS_SUSPENDED, $subscription->status);
        $this->assertSame(Subscription::SUSPENSION_REASON_BILLING, $subscription->suspension_reason);
        $this->assertFalse($tenant->is_active);
        $this->assertFalse($subscription->grantsPlatformAccess());
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('users', ['tenant_id' => $tenant->id]);

        Notification::assertSentTo(
            User::query()->where('email', 'admin@test.com')->first(),
            SubscriptionSuspendedNotification::class,
        );
    }

    public function test_scheduled_job_suspends_expired_past_due_subscriptions(): void
    {
        $tenant = $this->createTenantWithAdmin();
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => Subscription::STATUS_PAST_DUE,
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => 29900,
            'currency' => 'EUR',
            'grace_period_ends_at' => now()->subHour(),
        ]);

        (new SuspendPastDueSubscriptionsJob)->handle(app(SubscriptionBillingService::class));

        $this->assertSame(Subscription::STATUS_SUSPENDED, $tenant->fresh()->subscription->status);
        $this->assertFalse($tenant->fresh()->is_active);
    }

    public function test_payment_recovery_reactivates_subscription_and_tenant(): void
    {
        Notification::fake();

        $tenant = $this->createTenantWithAdmin();
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => Subscription::STATUS_SUSPENDED,
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => 29900,
            'currency' => 'EUR',
            'suspension_reason' => Subscription::SUSPENSION_REASON_BILLING,
            'suspended_at' => now()->subDay(),
            'past_due_at' => now()->subDays(8),
            'grace_period_ends_at' => now()->subDays(1),
        ]);
        $tenant->update(['is_active' => false]);

        app(SubscriptionBillingService::class)->handlePaymentRecovered($subscription);

        $subscription->refresh();
        $tenant->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->past_due_at);
        $this->assertNull($subscription->grace_period_ends_at);
        $this->assertNull($subscription->suspended_at);
        $this->assertTrue($tenant->is_active);
        $this->assertTrue($subscription->grantsPlatformAccess());

        Notification::assertSentTo(
            User::query()->where('email', 'admin@test.com')->first(),
            SubscriptionReactivatedNotification::class,
        );
    }

    public function test_grace_period_is_configurable(): void
    {
        config(['billing.grace_period_days' => 3]);

        $tenant = $this->createTenantWithAdmin();
        $subscription = $this->activateTenantSubscription($tenant);

        app(SubscriptionBillingService::class)->handlePaymentFailed($subscription);

        $subscription->refresh();

        $this->assertTrue(
            $subscription->grace_period_ends_at->equalTo(
                $subscription->past_due_at->copy()->addDays(3)
            )
        );
    }

    private function createTenantWithAdmin(): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Lido Test',
            'slug' => 'lido-test',
            'is_active' => true,
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return $tenant;
    }
}
