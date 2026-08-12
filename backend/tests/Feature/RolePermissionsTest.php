<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Beach',
            'slug' => 'test-beach',
            'is_active' => true,
        ]);

        $this->activateTenantSubscription($this->tenant);
    }

    private function createUser(string $role): User
    {
        $user = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => ucfirst($role),
            'email' => "{$role}@test.com",
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    public function test_admin_has_full_tenant_permissions(): void
    {
        $permissions = RolePermissions::forUser($this->createUser(User::ROLE_ADMIN));

        $this->assertContains(RolePermissions::USERS_MANAGE, $permissions);
        $this->assertContains(RolePermissions::MENU_MANAGE, $permissions);
        $this->assertContains(RolePermissions::ORDERS_MANAGE, $permissions);
        $this->assertContains(RolePermissions::QR_MANAGE, $permissions);
        $this->assertContains(RolePermissions::SETTINGS_MANAGE, $permissions);
        $this->assertNotContains(RolePermissions::TENANTS_MANAGE, $permissions);
    }

    public function test_manager_cannot_manage_users_or_settings(): void
    {
        $permissions = RolePermissions::forUser($this->createUser(User::ROLE_MANAGER));

        $this->assertContains(RolePermissions::MENU_MANAGE, $permissions);
        $this->assertContains(RolePermissions::ORDERS_MANAGE, $permissions);
        $this->assertContains(RolePermissions::QR_MANAGE, $permissions);
        $this->assertContains(RolePermissions::PRODUCTS_AVAILABILITY, $permissions);
        $this->assertNotContains(RolePermissions::USERS_MANAGE, $permissions);
        $this->assertNotContains(RolePermissions::SETTINGS_MANAGE, $permissions);
        $this->assertNotContains(RolePermissions::WAITER_CALLS_MANAGE, $permissions);
    }

    public function test_staff_can_only_view_and_update_orders_and_waiter_calls(): void
    {
        $permissions = RolePermissions::forUser($this->createUser(User::ROLE_STAFF));

        $this->assertSame([
            RolePermissions::ORDERS_VIEW,
            RolePermissions::ORDERS_UPDATE_STATUS,
            RolePermissions::WAITER_CALLS_MANAGE,
        ], $permissions);
    }

    public function test_staff_is_blocked_from_admin_endpoints(): void
    {
        $token = $this->createUser(User::ROLE_STAFF)->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/admin/products')
            ->assertForbidden();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/admin/settings')
            ->assertForbidden();
    }

    public function test_manager_can_manage_menu_and_qr_but_not_users(): void
    {
        $token = $this->createUser(User::ROLE_MANAGER)->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/admin/products')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/admin/locations')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_staff_cannot_update_payment(): void
    {
        $location = Location::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Table 1',
            'slug' => 'table-1',
            'type' => 'table',
            'code' => 'table1',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
            'order_number' => 'BO-TEST-PAY',
            'status' => 'delivered',
            'customer_session' => '00000000-0000-4000-8000-000000000001',
            'subtotal' => 10,
            'total' => 10,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $token = $this->createUser(User::ROLE_STAFF)->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$order->id}/payment", ['payment_status' => 'paid'])
            ->assertForbidden();
    }

    public function test_me_includes_permissions(): void
    {
        $token = $this->createUser(User::ROLE_MANAGER)->issueStaffToken();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('role', User::ROLE_MANAGER)
            ->assertJsonStructure(['permissions']);
    }
}
