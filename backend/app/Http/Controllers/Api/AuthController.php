<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OtpService;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionBillingService;
use App\Support\RolePermissions;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly SubscriptionBillingService $billing,
        private readonly StripeSubscriptionService $stripeSubscriptions,
        private readonly OtpService $otp,
    ) {}

    /**
     * Public self-registration: creates a tenant from ragione sociale + admin user.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'default_locale' => ['nullable', 'string', 'in:it,en,el,de'],
            'accept_terms' => ['required', 'accepted'],
        ]);

        if (! empty($data['slug']) && in_array(strtolower($data['slug']), Tenant::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => ['Questo slug non è disponibile.'],
            ]);
        }

        if (! $this->stripeSubscriptions->isConfigured()) {
            throw ValidationException::withMessages([
                'company_name' => ['Il servizio di abbonamento non è disponibile al momento. Riprova più tardi.'],
            ]);
        }

        $tenant = null;
        $user = null;

        DB::transaction(function () use ($data, &$tenant, &$user) {
            $slug = ! empty($data['slug'])
                ? strtolower($data['slug'])
                : Tenant::uniqueSlugFromName($data['company_name']);

            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $slug,
                'timezone' => 'Europe/Rome',
                'currency' => 'EUR',
                'default_locale' => $data['default_locale'] ?? 'it',
                'branding' => [
                    'tagline' => $data['company_name'],
                ],
                'settings' => Tenant::defaultSettings([
                    'loyalty_enabled' => false,
                    'country' => 'IT',
                ]),
                'is_active' => false,
            ]);

            $this->billing->upsertForTenant($tenant, [
                'status' => Subscription::STATUS_INACTIVE,
                'plan' => Subscription::PLAN_ANNUAL,
                'price_cents' => (int) config('billing.annual_price_cents', 29900),
                'currency' => 'EUR',
            ]);

            TenantContext::set($tenant);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'terms_accepted_at' => now(),
            ]);

            AuditLogger::log('tenant.registered', $tenant, null, $tenant->toArray());
            AuditLogger::log('user.created', $user, null, $user->toArray());
        });

        $user->sendEmailVerificationNotification();
        $user->load(['tenant.subscription']);
        $token = $user->issueStaffToken();

        try {
            $checkout = $this->stripeSubscriptions->createCheckoutSession($tenant, $user, 'registration');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Account creato, ma non è stato possibile avviare il pagamento. Accedi e riprova.',
                'token' => $token,
                'user' => $this->userPayload($user),
                'checkout_url' => null,
            ], 502);
        }

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'checkout_url' => $checkout['url'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant' => ['nullable', 'string'], // optional tenant slug hint
        ]);

        /** @var User|null $user */
        $query = User::query()->where('email', $credentials['email']);

        if (! empty($credentials['tenant'])) {
            $query->whereHas('tenant', fn ($q) => $q->where('slug', $credentials['tenant']));
        }

        $user = $query->first();

        $invalid = ! $user
            || ! Hash::check($credentials['password'], $user->password)
            || ! $user->is_active
            || (! $user->isSuperAdmin() && $user->tenant && $user->tenant->isAdminSuspended());

        if ($invalid) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user->load(['tenant.subscription']);
        $token = $user->issueStaffToken();

        AuditLogger::log('auth.login', $user, null, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_active) {
            $this->deleteCurrentAccessToken($user);
            Auth::forgetGuards();

            return response()->json(['message' => 'Account disabled'], 403);
        }

        $user->load(['tenant.subscription']);

        if (! $user->isSuperAdmin() && $user->tenant && $user->tenant->isAdminSuspended()) {
            $this->deleteCurrentAccessToken($user);
            Auth::forgetGuards();

            return response()->json([
                'message' => 'Tenant inactive',
                'code' => 'tenant_inactive',
            ], 403);
        }

        return response()->json($this->userPayload($user));
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $payload = array_merge($user->toArray(), [
            'permissions' => RolePermissions::forUser($user),
        ]);

        if (is_array($payload['tenant'] ?? null) && is_array($payload['tenant']['branding'] ?? null)) {
            $payload['tenant']['branding'] = TenantBranding::resolve($payload['tenant']['branding']);
        }

        return $payload;
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        AuditLogger::log('auth.logout', $user);
        $this->deleteCurrentAccessToken($user);
        Auth::forgetGuards();

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->revokeAllTokens();
        AuditLogger::log('auth.logout_all', $user);
        Auth::forgetGuards();

        return response()->json(['message' => 'Logged out from all devices']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        $current = $user->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->id : null;
        $user->tokens()
            ->when($currentId, fn ($q) => $q->whereKeyNot($currentId))
            ->when(! $currentId, fn ($q) => $q)
            ->delete();

        AuditLogger::log('auth.password_changed', $user);

        return response()->json(['message' => 'Password updated']);
    }

    private function deleteCurrentAccessToken(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Always return the same response to avoid email enumeration.
        $user = User::query()->where('email', $request->input('email'))->first();
        if ($user && $user->is_active) {
            $this->otp->send($user, OtpService::PURPOSE_PASSWORD_RESET);
        }

        return response()->json([
            'message' => 'If an account exists for that email, a reset code has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $this->otp->verifyOrFail(
            OtpService::PURPOSE_PASSWORD_RESET,
            $data['email'],
            $data['code'],
        );

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('Unable to reset password for this account.')],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        $user->revokeAllTokens();

        event(new PasswordReset($user));
        AuditLogger::log('auth.password_reset', $user);

        return response()->json(['message' => 'Password has been reset']);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        if (! URL::hasValidSignature($request)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid or expired verification link.'],
            ]);
        }

        $user = User::query()->findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid verification link.'],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
        AuditLogger::log('auth.email_verified', $user);

        return response()->json(['message' => 'Email verified']);
    }

    public function verifyEmailWithOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $this->otp->verifyOrFail(
            OtpService::PURPOSE_EMAIL_VERIFICATION,
            $user->email,
            $data['code'],
        );

        $user->markEmailAsVerified();
        event(new Verified($user));
        AuditLogger::log('auth.email_verified', $user);

        return response()->json(['message' => 'Email verified']);
    }

    public function sendVerificationNotification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification code sent']);
    }
}
