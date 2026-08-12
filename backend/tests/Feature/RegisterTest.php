<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.stripe.secret' => 'sk_test_fake',
            'billing.stripe.price_annual' => 'price_test_annual',
        ]);

        $this->mock(StripeSubscriptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createCheckoutSession')
                ->andReturn(['url' => 'https://checkout.stripe.test/session']);
        });
    }

    public function test_public_registration_creates_tenant_and_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'company_name' => 'Lido Sole SRL',
            'name' => 'Mario Rossi',
            'email' => 'mario@lido-sole.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'default_locale' => 'it',
            'accept_terms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'mario@lido-sole.test')
            ->assertJsonPath('user.role', User::ROLE_ADMIN)
            ->assertJsonPath('user.tenant.name', 'Lido Sole SRL')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.test/session')
            ->assertJsonStructure(['token', 'user', 'checkout_url']);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Lido Sole SRL',
            'slug' => 'lido-sole-srl',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mario@lido-sole.test',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_registration_rejects_when_subscription_billing_unavailable(): void
    {
        $this->mock(StripeSubscriptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->postJson('/api/register', [
            'company_name' => 'Nuovo Lido',
            'name' => 'Owner',
            'email' => 'owner@nuovo-lido.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['company_name']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Existing',
            'slug' => 'existing',
            'is_active' => true,
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Admin',
            'email' => 'taken@test.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->postJson('/api/register', [
            'company_name' => 'Nuovo Lido',
            'name' => 'Other',
            'email' => 'taken@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_registration_auto_suffixes_duplicate_slug(): void
    {
        Tenant::query()->create([
            'name' => 'Blue Beach',
            'slug' => 'blue-beach',
            'is_active' => true,
        ]);

        $this->postJson('/api/register', [
            'company_name' => 'Blue Beach',
            'name' => 'Admin',
            'email' => 'blue2@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ])->assertCreated()
            ->assertJsonPath('user.tenant.slug', 'blue-beach-2');
    }
}
