<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * US-04 — Tenant isolation acceptance tests.
 *
 * Tenant A → own data only; Tenant A ↛ Tenant B; Tenant B ↛ Tenant A.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private Category $categoryA;

    private Category $categoryB;

    private Product $productA;

    private Product $productB;

    private Location $locationA;

    private Location $locationB;

    private Order $orderA;

    private Order $orderB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::query()->create([
            'name' => 'Sunset Lido',
            'slug' => 'sunset-lido',
            'is_active' => true,
        ]);

        $this->adminA = $this->makeAdmin($this->tenantA, 'admin-a@test.com');
        $this->adminB = $this->makeAdmin($this->tenantB, 'admin-b@test.com');

        TenantContext::set($this->tenantA);
        $this->categoryA = Category::query()->create([
            'name' => ['it' => 'Cat A'],
            'slug' => 'cat-a',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->productA = Product::query()->create([
            'category_id' => $this->categoryA->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Product A'],
            'slug' => 'product-a',
            'price' => 10,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);
        $this->locationA = Location::query()->create([
            'name' => 'Loc A',
            'slug' => 'loc-a',
            'type' => 'umbrella',
            'code' => 'code-a',
            'is_active' => true,
        ]);
        $this->orderA = Order::query()->create([
            'order_number' => 'BO-A-1',
            'location_id' => $this->locationA->id,
            'status' => 'received',
            'customer_session' => 'session-a',
            'subtotal' => 10,
            'total' => 10,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);
        $this->userA = User::query()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff A',
            'email' => 'staff-a@test.com',
            'password' => 'password123',
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $this->userA->markEmailAsVerified();

        TenantContext::set($this->tenantB);
        $this->categoryB = Category::query()->create([
            'name' => ['it' => 'Cat B'],
            'slug' => 'cat-b',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->productB = Product::query()->create([
            'category_id' => $this->categoryB->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Product B'],
            'slug' => 'product-b',
            'price' => 12,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);
        $this->locationB = Location::query()->create([
            'name' => 'Loc B',
            'slug' => 'loc-b',
            'type' => 'umbrella',
            'code' => 'code-b',
            'is_active' => true,
        ]);
        $this->orderB = Order::query()->create([
            'order_number' => 'BO-B-1',
            'location_id' => $this->locationB->id,
            'status' => 'received',
            'customer_session' => 'session-b',
            'subtotal' => 12,
            'total' => 12,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);
        $this->userB = User::query()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff B',
            'email' => 'staff-b@test.com',
            'password' => 'password123',
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $this->userB->markEmailAsVerified();

        TenantContext::clear();

        $this->activateTenantSubscription($this->tenantA);
        $this->activateTenantSubscription($this->tenantB);
    }

    public function test_tenant_a_can_access_own_admin_resources(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['slug' => 'product-a']);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['slug' => 'cat-a']);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/locations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['code' => 'code-a']);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'staff-a@test.com']);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_tenant_a_cannot_access_tenant_b_resources_by_id(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products/'.$this->productB->id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/categories/'.$this->categoryB->id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/locations/'.$this->locationB->id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->patchJson('/api/orders/'.$this->orderB->id.'/status', ['status' => 'accepted'])
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->putJson('/api/admin/users/'.$this->userB->id, ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_tenant_b_cannot_access_tenant_a_resources_by_id(): void
    {
        $token = $this->adminB->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->getJson('/api/admin/products/'.$this->productA->id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->getJson('/api/admin/categories/'.$this->categoryA->id)
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->patchJson('/api/orders/'.$this->orderA->id.'/status', ['status' => 'accepted'])
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->deleteJson('/api/admin/users/'.$this->userA->id)
            ->assertNotFound();
    }

    public function test_tenant_a_cannot_spoof_tenant_b_via_header(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->getJson('/api/admin/products')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant access denied');
    }

    public function test_tenant_b_cannot_spoof_tenant_a_via_header(): void
    {
        $token = $this->adminB->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant access denied');
    }

    public function test_cannot_reassign_entity_to_another_tenant_via_request_body(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->postJson('/api/admin/products', [
                'tenant_id' => $this->tenantB->id,
                'category_id' => $this->categoryA->id,
                'name' => ['en' => 'Sneaky'],
                'price' => 5,
                'station' => 'kitchen',
            ])
            ->assertCreated();

        $created = Product::withoutGlobalScopes()
            ->where('name->en', 'Sneaky')
            ->latest('id')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame($this->tenantA->id, (int) $created->tenant_id);
    }

    public function test_cannot_move_product_to_other_tenant_on_update(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->putJson('/api/admin/products/'.$this->productA->id, [
                'tenant_id' => $this->tenantB->id,
                'name' => ['en' => 'Product A Renamed'],
            ])
            ->assertOk();

        $this->assertSame($this->tenantA->id, (int) $this->productA->fresh()->tenant_id);
    }

    public function test_staff_token_cannot_bypass_session_on_public_order_show(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->getJson('/api/t/azure-beach/orders/'.$this->orderA->id)
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/t/sunset-lido/orders/'.$this->orderB->id)
            ->assertForbidden();
    }

    public function test_customer_session_still_grants_access_without_staff_token(): void
    {
        $this->getJson('/api/t/azure-beach/orders/'.$this->orderA->id.'?session=session-a')
            ->assertOk()
            ->assertJsonPath('order_number', 'BO-A-1');

        $this->getJson('/api/t/azure-beach/orders/'.$this->orderA->id.'?session=wrong-session')
            ->assertForbidden();
    }

    public function test_tenant_admin_cannot_access_platform_endpoints(): void
    {
        $tokenA = $this->adminA->issueStaffToken();
        $tokenB = $this->adminB->issueStaffToken();

        $this->withToken($tokenA)
            ->getJson('/api/platform/overview')
            ->assertForbidden();

        $this->withToken($tokenB)
            ->getJson('/api/platform/tenants')
            ->assertForbidden();

        $this->withToken($tokenA)
            ->postJson('/api/platform/tenants', ['name' => 'Evil Tenant'])
            ->assertForbidden();
    }

    public function test_public_menu_is_scoped_to_url_tenant(): void
    {
        $this->getJson('/api/t/azure-beach/menu')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'product-a'])
            ->assertJsonMissing(['slug' => 'product-b']);

        $this->getJson('/api/t/sunset-lido/menu')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'product-b'])
            ->assertJsonMissing(['slug' => 'product-a']);
    }

    public function test_list_endpoints_never_leak_other_tenant_rows(): void
    {
        $tokenA = $this->adminA->issueStaffToken();
        $tokenB = $this->adminB->issueStaffToken();

        $this->withToken($tokenA)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'product-b']);

        Auth::forgetGuards();

        $this->withToken($tokenB)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'product-a']);
    }

    private function makeAdmin(Tenant $tenant, string $email): User
    {
        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin '.$tenant->slug,
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        return $admin;
    }
}
