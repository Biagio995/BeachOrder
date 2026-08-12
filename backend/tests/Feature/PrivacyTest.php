<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_info_is_public(): void
    {
        $this->getJson('/api/privacy/info')
            ->assertOk()
            ->assertJsonStructure([
                'controller',
                'processor',
                'retention',
                'data_inventory',
                'legal_documents',
            ]);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->postJson('/api/register', [
            'company_name' => 'Lido Test',
            'name' => 'Mario',
            'email' => 'mario@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['accept_terms']);
    }

    public function test_staff_can_export_personal_data(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Bar',
            'slug' => 'test-bar',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $token = $user->issueStaffToken();

        $this->getJson('/api/me/export', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('profile.email', 'admin@test.com')
            ->assertJsonPath('subject_type', 'staff_user');
    }

    public function test_staff_can_delete_own_account(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Bar',
            'slug' => 'test-bar-2',
            'is_active' => true,
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Other Admin',
            'email' => 'other@test.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Deletable Admin',
            'email' => 'delete@test.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $token = $user->issueStaffToken();

        $this->deleteJson('/api/me/account', [
            'password' => 'password123',
            'confirm' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant' => $tenant->slug,
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'delete@test.com']);
    }

    public function test_legal_document_endpoint_serves_markdown(): void
    {
        $this->get('/api/legal/privacy?locale=it')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    }
}
