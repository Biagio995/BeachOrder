<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_see_cross_tenant_overview(): void
    {
        $super = User::query()->create([
            'name' => 'Super',
            'email' => 'super@test.com',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
            'is_active' => true,
        ]);
        $super->markEmailAsVerified();

        $a = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
        ]);

        $b = Tenant::query()->create([
            'name' => 'Sunset Lido',
            'slug' => 'sunset-lido',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'el',
        ]);

        $locA = Location::withoutGlobalScopes()->create([
            'tenant_id' => $a->id,
            'name' => 'Ombrellone 1',
            'slug' => 'ombrellone-1',
            'type' => 'umbrella',
            'code' => 'umbrella1',
            'is_active' => true,
        ]);

        $locB = Location::withoutGlobalScopes()->create([
            'tenant_id' => $b->id,
            'name' => 'Ombrellone 2',
            'slug' => 'ombrellone-2',
            'type' => 'umbrella',
            'code' => 'umbrella2',
            'is_active' => true,
        ]);

        Order::withoutGlobalScopes()->create([
            'tenant_id' => $a->id,
            'order_number' => 'BO-1',
            'location_id' => $locA->id,
            'status' => 'delivered',
            'customer_session' => 'sess-a',
            'subtotal' => 20,
            'total' => 20,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ]);

        Order::withoutGlobalScopes()->create([
            'tenant_id' => $b->id,
            'order_number' => 'BO-2',
            'location_id' => $locB->id,
            'status' => 'delivered',
            'customer_session' => 'sess-b',
            'subtotal' => 35,
            'total' => 35,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ]);

        Sanctum::actingAs($super);

        $this->getJson('/api/platform/overview?days=7')
            ->assertOk()
            ->assertJsonPath('kpis.tenants_total', 2)
            ->assertJsonPath('kpis.orders', 2)
            ->assertJsonPath('kpis.revenue', 55)
            ->assertJsonCount(2, 'tenants');
    }

    public function test_tenant_admin_cannot_access_platform_overview(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        Sanctum::actingAs($admin);

        $this->getJson('/api/platform/overview')
            ->assertForbidden();
    }
}
