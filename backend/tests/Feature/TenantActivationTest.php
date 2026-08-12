<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantActivationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
            'branding' => ['tagline' => 'Azure Beach Resort'],
        ]);

        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->admin->markEmailAsVerified();

        $this->superAdmin = User::query()->create([
            'name' => 'Super',
            'email' => 'super@test.com',
            'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
            'is_active' => true,
        ]);
        $this->superAdmin->markEmailAsVerified();

        Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ombrellone 1',
            'slug' => 'ombrellone-1',
            'type' => 'umbrella',
            'code' => 'umbrella1',
            'is_active' => true,
        ]);
    }

    private function suspendTenantViaPlatform(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->putJson("/api/platform/tenants/{$this->tenant->id}", ['is_active' => false])
            ->assertOk();

        Auth::forgetGuards();
        $this->tenant->refresh();
    }

    public function test_public_status_endpoint_reports_inactive_tenant(): void
    {
        $this->suspendTenantViaPlatform();

        $this->getJson('/api/t/azure-beach/status')
            ->assertOk()
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('admin_suspended', true)
            ->assertJsonPath('tenant.name', 'Azure Beach');
    }

    public function test_inactive_tenant_public_menu_returns_tenant_inactive(): void
    {
        $this->suspendTenantViaPlatform();

        $this->getJson('/api/t/azure-beach/menu')
            ->assertForbidden()
            ->assertJsonPath('code', 'tenant_inactive')
            ->assertJsonPath('tenant.slug', 'azure-beach');
    }

    public function test_staff_cannot_login_when_tenant_admin_suspended(): void
    {
        $this->suspendTenantViaPlatform();

        $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ])->assertUnprocessable();
    }

    public function test_staff_api_blocked_when_tenant_admin_suspended(): void
    {
        $token = $this->admin->issueStaffToken();
        $this->suspendTenantViaPlatform();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertUnauthorized();
    }

    public function test_me_rejects_revoked_token_after_tenant_admin_suspended(): void
    {
        $token = $this->admin->issueStaffToken();
        $this->suspendTenantViaPlatform();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_deactivate_tenant_and_revoke_staff_tokens(): void
    {
        $token = $this->admin->issueStaffToken();
        $this->suspendTenantViaPlatform();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertUnauthorized();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->admin->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_super_admin_can_manage_inactive_tenant(): void
    {
        $this->suspendTenantViaPlatform();
        Sanctum::actingAs($this->superAdmin);

        $this->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertOk();
    }

    public function test_super_admin_can_reactivate_tenant(): void
    {
        $this->suspendTenantViaPlatform();
        Sanctum::actingAs($this->superAdmin);

        $this->putJson("/api/platform/tenants/{$this->tenant->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->getJson('/api/t/azure-beach/menu')
            ->assertOk();
    }
}
