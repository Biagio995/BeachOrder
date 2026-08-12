<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Lido Test',
            'slug' => 'lido-test',
            'is_active' => false,
        ]);

        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@lido-test.test',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->admin->markEmailAsVerified();
    }

    public function test_subscription_show_returns_inactive_when_no_record(): void
    {
        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'lido-test')
            ->getJson('/api/admin/subscription')
            ->assertOk()
            ->assertJsonPath('status', Subscription::STATUS_INACTIVE)
            ->assertJsonPath('pricing.amount_cents', 29900)
            ->assertJsonPath('grants_access', false);
    }

    public function test_subscription_show_returns_backend_state(): void
    {
        $this->activateTenantSubscription($this->tenant);

        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'lido-test')
            ->getJson('/api/admin/subscription')
            ->assertOk()
            ->assertJsonPath('status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('grants_access', true)
            ->assertJsonStructure([
                'started_at',
                'next_renewal_at',
                'current_period_end',
            ]);
    }

    public function test_admin_routes_blocked_without_active_subscription(): void
    {
        Subscription::query()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Subscription::STATUS_INACTIVE,
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => 29900,
            'currency' => 'EUR',
        ]);

        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'lido-test')
            ->getJson('/api/admin/products')
            ->assertStatus(402)
            ->assertJsonPath('subscription.status', Subscription::STATUS_INACTIVE);
    }

    public function test_checkout_requires_stripe_configuration(): void
    {
        config([
            'billing.stripe.secret' => null,
            'billing.stripe.price_annual' => null,
        ]);

        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'lido-test')
            ->postJson('/api/admin/subscription/checkout')
            ->assertStatus(503);
    }

    public function test_registration_creates_inactive_tenant_and_subscription(): void
    {
        config([
            'billing.stripe.secret' => 'sk_test_fake',
            'billing.stripe.price_annual' => 'price_test_annual',
        ]);

        $this->mock(\App\Services\StripeSubscriptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createCheckoutSession')
                ->andReturn(['url' => 'https://checkout.stripe.test/session']);
        });

        $this->postJson('/api/register', [
            'company_name' => 'Nuovo Bar',
            'name' => 'Owner',
            'email' => 'owner@nuovo-bar.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Nuovo Bar',
            'is_active' => false,
        ]);

        $tenant = Tenant::query()->where('slug', 'nuovo-bar')->first();
        $this->assertNotNull($tenant);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => Subscription::STATUS_INACTIVE,
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => 29900,
        ]);
    }

    public function test_demo_tenant_bypasses_subscription_middleware(): void
    {
        $demoTenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'is_demo' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $demoTenant->id,
            'name' => 'Demo Admin',
            'email' => 'admin@azure.test',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        $token = $admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertOk();
    }

    public function test_demo_tenant_subscription_payload_grants_access(): void
    {
        $demoTenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'is_demo' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $demoTenant->id,
            'name' => 'Demo Admin',
            'email' => 'admin@azure.test',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        $token = $admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/subscription')
            ->assertOk()
            ->assertJsonPath('grants_access', true)
            ->assertJsonPath('status', Subscription::STATUS_ACTIVE);
    }
}
