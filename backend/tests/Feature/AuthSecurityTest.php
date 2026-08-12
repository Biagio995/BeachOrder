<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Services\OtpService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

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

        $this->adminA = User::query()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin-a@test.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->adminA->markEmailAsVerified();

        $this->activateTenantSubscription($this->tenantA);
        $this->activateTenantSubscription($this->tenantB);

        $this->adminB = User::query()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'admin-b@test.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->adminB->markEmailAsVerified();
    }

    public function test_login_returns_token_and_hashed_password_is_not_plain(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin-a@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role', 'tenant']]);

        $this->assertNotEquals('password123', $this->adminA->fresh()->password);
        $this->assertTrue(Hash::check('password123', $this->adminA->fresh()->password));
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_user_cannot_spoof_another_tenant_via_header(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'sunset-lido')
            ->getJson('/api/admin/products')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant access denied');
    }

    public function test_admin_cannot_see_other_tenant_products(): void
    {
        TenantContext::set($this->tenantA);
        $categoryA = Category::query()->create([
            'name' => ['it' => 'Cat A'],
            'slug' => 'cat-a',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $categoryA->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Product A'],
            'slug' => 'product-a',
            'price' => 10,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        TenantContext::set($this->tenantB);
        $categoryB = Category::query()->create([
            'name' => ['it' => 'Cat B'],
            'slug' => 'cat-b',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'category_id' => $categoryB->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Product B'],
            'slug' => 'product-b',
            'price' => 12,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);
        TenantContext::clear();

        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonMissing(['slug' => 'product-b']);

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products/'.$productB->id)
            ->assertNotFound();
    }

    public function test_role_middleware_blocks_staff_from_admin(): void
    {
        $staff = User::query()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff',
            'email' => 'staff-a@test.com',
            'password' => 'password123',
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $staff->markEmailAsVerified();

        $token = $staff->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertForbidden();
    }

    public function test_disabled_user_cannot_use_api(): void
    {
        $this->adminA->update(['is_active' => false]);
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account disabled');
    }

    public function test_unauthenticated_requests_to_staff_apis_are_rejected(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', [
            'email' => 'admin-a@test.com',
        ])->assertOk();

        Notification::assertSentTo($this->adminA, OtpCodeNotification::class, function (OtpCodeNotification $n) {
            return $n->purpose === OtpService::PURPOSE_PASSWORD_RESET
                && preg_match('/^\d{6}$/', $n->code) === 1;
        });
    }

    public function test_forgot_password_does_not_reveal_missing_email(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', [
            'email' => 'nobody@test.com',
        ])->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a reset code has been sent.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_revokes_tokens(): void
    {
        Notification::fake();
        $token = $this->adminA->issueStaffToken();

        $this->postJson('/api/forgot-password', [
            'email' => 'admin-a@test.com',
        ])->assertOk();

        $code = null;
        Notification::assertSentTo($this->adminA, OtpCodeNotification::class, function (OtpCodeNotification $n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->postJson('/api/reset-password', [
            'email' => 'admin-a@test.com',
            'code' => $code,
            'password' => 'new-password-99',
            'password_confirmation' => 'new-password-99',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-99', $this->adminA->fresh()->password));
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => 'admin-a@test.com',
            'password' => 'new-password-99',
        ])->assertOk();
    }

    public function test_change_password_keeps_current_token_and_revokes_others(): void
    {
        $keep = $this->adminA->issueStaffToken();
        $other = $this->adminA->issueStaffToken();

        $this->withToken($keep)
            ->withHeader('X-Tenant', 'azure-beach')
            ->postJson('/api/change-password', [
                'current_password' => 'password123',
                'password' => 'changed-password-1',
                'password_confirmation' => 'changed-password-1',
            ])
            ->assertOk();

        Auth::forgetGuards();
        $this->withToken($keep)->withHeader('X-Tenant', 'azure-beach')->getJson('/api/me')->assertOk();
        Auth::forgetGuards();
        $this->withToken($other)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_can_access_tenant_helper(): void
    {
        $this->assertTrue($this->adminA->canAccessTenant($this->tenantA));
        $this->assertFalse($this->adminA->canAccessTenant($this->tenantB));

        $super = User::query()->create([
            'tenant_id' => null,
            'name' => 'Super',
            'email' => 'super@test.com',
            'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $super->markEmailAsVerified();

        $this->assertTrue($super->canAccessTenant($this->tenantA));
        $this->assertTrue($super->canAccessTenant($this->tenantB));
    }

    public function test_disabled_user_login_returns_generic_error(): void
    {
        $this->adminA->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => 'admin-a@test.com',
            'password' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Invalid credentials.');
    }

    public function test_login_failure_does_not_reveal_missing_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@test.com',
            'password' => 'password123',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Invalid credentials.');
    }

    public function test_unverified_user_cannot_access_staff_routes(): void
    {
        $this->adminA->forceFill(['email_verified_at' => null])->save();
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertForbidden();
    }

    public function test_verified_user_can_access_staff_routes(): void
    {
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/products')
            ->assertOk();
    }

    public function test_email_verification_marks_user_verified(): void
    {
        $this->adminA->forceFill(['email_verified_at' => null])->save();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $this->adminA->id, 'hash' => sha1($this->adminA->email)]
        );

        $this->getJson($url)->assertOk();

        $this->assertNotNull($this->adminA->fresh()->email_verified_at);
    }

    public function test_verification_notification_is_sent(): void
    {
        Notification::fake();

        $this->adminA->forceFill(['email_verified_at' => null])->save();
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->postJson('/api/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($this->adminA, OtpCodeNotification::class, function (OtpCodeNotification $n) {
            return $n->purpose === OtpService::PURPOSE_EMAIL_VERIFICATION;
        });
    }

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'company_name' => 'New Beach',
            'name' => 'Owner',
            'email' => 'owner@new-beach.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => true,
        ])->assertCreated();

        $user = User::query()->where('email', 'owner@new-beach.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, OtpCodeNotification::class, function (OtpCodeNotification $n) {
            return $n->purpose === OtpService::PURPOSE_EMAIL_VERIFICATION;
        });
    }

    public function test_email_can_be_verified_with_otp(): void
    {
        Notification::fake();

        $this->adminA->forceFill(['email_verified_at' => null])->save();
        $token = $this->adminA->issueStaffToken();

        $this->withToken($token)
            ->postJson('/api/email/verification-notification')
            ->assertOk();

        $code = null;
        Notification::assertSentTo($this->adminA, OtpCodeNotification::class, function (OtpCodeNotification $n) use (&$code) {
            $code = $n->code;

            return $n->purpose === OtpService::PURPOSE_EMAIL_VERIFICATION;
        });

        $this->withToken($token)
            ->postJson('/api/email/verify-otp', ['code' => $code])
            ->assertOk();

        $this->assertNotNull($this->adminA->fresh()->email_verified_at);
    }
}
