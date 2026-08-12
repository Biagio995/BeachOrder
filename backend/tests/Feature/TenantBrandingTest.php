<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * US-14 / US-21 — Restaurant white-label branding per tenant.
 */
class TenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->tenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'branding' => [
                'primary_color' => '#0B6E6B',
                'accent_color' => '#E07A5F',
                'tagline' => 'Ordina dall\'ombrellone',
            ],
        ]);

        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@azure.test',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->admin->markEmailAsVerified();

        $this->activateTenantSubscription($this->tenant);
    }

    public function test_admin_can_update_branding_colors_and_tagline(): void
    {
        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->putJson('/api/admin/settings', [
                'name' => 'Azure Beach Club',
                'branding' => [
                    'primary_color' => '#123456',
                    'accent_color' => '#654321',
                    'tagline' => 'Welcome aboard',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Azure Beach Club')
            ->assertJsonPath('branding.primary_color', '#123456')
            ->assertJsonPath('branding.accent_color', '#654321')
            ->assertJsonPath('branding.tagline', 'Welcome aboard');
    }

    public function test_admin_can_upload_logo_and_public_menu_exposes_url(): void
    {
        $token = $this->admin->issueStaffToken();
        $file = UploadedFile::fake()->image('logo.png', 120, 120);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->post('/api/admin/branding/logo', ['logo' => $file])
            ->assertOk()
            ->assertJsonStructure(['branding' => ['logo_url']]);

        $this->tenant->refresh();
        $this->assertNotEmpty($this->tenant->branding['logo_path'] ?? null);

        $this->getJson('/api/t/azure-beach/status')
            ->assertOk()
            ->assertJsonPath('tenant.name', 'Azure Beach')
            ->assertJsonPath('tenant.branding.primary_color', '#0B6E6B')
            ->assertJsonStructure(['tenant' => ['branding' => ['logo_url']]]);
    }

    public function test_tenant_branding_resolves_storage_paths_to_urls(): void
    {
        $path = 'tenants/1/branding/logo/test.png';
        Storage::disk('public')->put($path, 'fake');

        $resolved = TenantBranding::resolve([
            'logo_path' => $path,
            'primary_color' => '#0B6E6B',
        ]);

        $this->assertSame('/storage/'.$path, $resolved['logo_url']);
        $this->assertSame('#0B6E6B', $resolved['primary_color']);
    }

    public function test_status_endpoint_hydrates_tenant_for_customer_routes(): void
    {
        $this->getJson('/api/t/azure-beach/status')
            ->assertOk()
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('tenant.slug', 'azure-beach')
            ->assertJsonPath('tenant.restaurant_name', 'Azure Beach')
            ->assertJsonPath('tenant.currency', 'EUR')
            ->assertJsonStructure([
                'tenant' => [
                    'id',
                    'name',
                    'restaurant_name',
                    'slug',
                    'currency',
                    'default_locale',
                    'branding',
                    'settings' => ['online_payments_enabled'],
                ],
            ]);
    }

    public function test_branding_aliases_are_exposed_in_api_responses(): void
    {
        $this->tenant->update([
            'branding' => array_merge($this->tenant->branding ?? [], [
                'accent_color' => '#654321',
            ]),
        ]);

        $this->getJson('/api/t/azure-beach/status')
            ->assertOk()
            ->assertJsonPath('tenant.branding.accent_color', '#654321')
            ->assertJsonPath('tenant.branding.secondary_color', '#654321');
    }

    public function test_admin_can_save_branding_with_us21_alias_fields(): void
    {
        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->putJson('/api/admin/settings', [
                'branding' => [
                    'primary_color' => '#111111',
                    'secondary_color' => '#222222',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('branding.primary_color', '#111111')
            ->assertJsonPath('branding.accent_color', '#222222')
            ->assertJsonPath('branding.secondary_color', '#222222');

        $this->tenant->refresh();
        $this->assertSame('#222222', $this->tenant->branding['accent_color'] ?? null);
        $this->assertArrayNotHasKey('secondary_color', $this->tenant->branding ?? []);
    }

    public function test_admin_can_upload_favicon_and_menu_cover(): void
    {
        $token = $this->admin->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->post('/api/admin/branding/favicon', [
                'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
            ])
            ->assertOk()
            ->assertJsonStructure(['branding' => ['favicon_url']]);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->post('/api/admin/branding/menu-header', [
                'menu_header' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
            ])
            ->assertOk()
            ->assertJsonStructure(['branding' => ['menu_header_url', 'menu_cover_url']]);
    }

    public function test_inactive_tenant_status_returns_resolved_branding(): void
    {
        $this->tenant->update(['is_active' => false]);

        $path = 'tenants/'.$this->tenant->id.'/branding/logo/test.png';
        Storage::disk('public')->put($path, 'fake');
        $this->tenant->update([
            'branding' => array_merge($this->tenant->branding ?? [], ['logo_path' => $path]),
        ]);

        $this->getJson('/api/t/azure-beach/status')
            ->assertOk()
            ->assertJsonPath('is_active', false)
            ->assertJsonStructure(['tenant' => ['branding' => ['logo_url']]]);
    }
}
