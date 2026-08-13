<?php

namespace App\Models;

use App\Services\OtpService;
use App\Support\RolePermissions;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements CanResetPasswordContract, MustVerifyEmailContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_STAFF = 'staff';

    public const STAFF_POSITION_KITCHEN = 'kitchen';

    public const STAFF_POSITION_BAR = 'bar';

    public const STAFF_POSITION_WAITER = 'waiter';

    /** @var list<string> */
    public const STAFF_POSITIONS = [
        self::STAFF_POSITION_KITCHEN,
        self::STAFF_POSITION_BAR,
        self::STAFF_POSITION_WAITER,
    ];

    public const STAFF_TOKEN_NAME = 'staff';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'staff_position',
        'is_active',
        'location_id',
        'terms_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->isSuperAdmin();
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isStaffRole(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function staffPosition(): ?string
    {
        return $this->isStaffRole() ? $this->staff_position : null;
    }

    public function hasStaffPosition(string $position): bool
    {
        return $this->staffPosition() === $position;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_STAFF,
        ], true);
    }

    public function hasPermission(string $permission): bool
    {
        return RolePermissions::userHas($this, $permission);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return RolePermissions::forUser($this);
    }

    /**
     * Explicit membership check — never trust a client-supplied tenant_id alone.
     */
    public function canAccessTenant(null|int|Tenant $tenant): bool
    {
        if (! $this->is_active || ! $this->isStaff()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $tenantId !== null && (int) $this->tenant_id === (int) $tenantId;
    }

    public function issueStaffToken(): string
    {
        $expiration = config('sanctum.expiration');
        $expiresAt = is_numeric($expiration)
            ? now()->addMinutes((int) $expiration)
            : null;

        return $this->createToken(self::STAFF_TOKEN_NAME, ['*'], $expiresAt)->plainTextToken;
    }

    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    /**
     * Email verification uses a 6-digit OTP instead of a signed URL.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(OtpService::class)->send($this, OtpService::PURPOSE_EMAIL_VERIFICATION);
    }

    /**
     * Password reset uses a 6-digit OTP (token argument ignored).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        app(OtpService::class)->send($this, OtpService::PURPOSE_PASSWORD_RESET);
    }
}
