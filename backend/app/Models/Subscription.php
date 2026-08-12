<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_INACTIVE = 'inactive';

    /** Internal billing suspension after grace period — exposed as expired to clients. */
    public const STATUS_SUSPENDED = 'suspended';

    public const SUSPENSION_REASON_BILLING = 'billing';

    public const PLAN_ANNUAL = 'annual';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAST_DUE,
        self::STATUS_CANCELED,
        self::STATUS_UNPAID,
        self::STATUS_EXPIRED,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'tenant_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
        'plan',
        'price_cents',
        'currency',
        'started_at',
        'current_period_start',
        'current_period_end',
        'past_due_at',
        'grace_period_ends_at',
        'canceled_at',
        'ended_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'past_due_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isSuspendedForBilling(): bool
    {
        return $this->status === self::STATUS_SUSPENDED
            && $this->suspension_reason === self::SUSPENSION_REASON_BILLING;
    }

    public function gracePeriodExpired(): bool
    {
        return $this->grace_period_ends_at !== null
            && $this->grace_period_ends_at->isPast();
    }

    public function inRecoverableState(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAST_DUE,
            self::STATUS_SUSPENDED,
            self::STATUS_UNPAID,
        ], true);
    }

    public function grantsPlatformAccess(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PAST_DUE], true);
    }

    public function publicStatus(): string
    {
        if ($this->status === self::STATUS_SUSPENDED) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'status' => $this->publicStatus(),
            'plan' => $this->plan ?: self::PLAN_ANNUAL,
            'price_cents' => $this->price_cents ?: (int) config('billing.annual_price_cents', 29900),
            'currency' => $this->currency ?: 'EUR',
            'started_at' => $this->started_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'next_renewal_at' => $this->current_period_end?->toIso8601String(),
            'expires_at' => $this->resolveExpiresAt()?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'grants_access' => $this->grantsPlatformAccess(),
        ];
    }

    public function resolveExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->ended_at) {
            return $this->ended_at;
        }

        if ($this->isSuspendedForBilling()) {
            return $this->suspended_at ?? $this->current_period_end;
        }

        if (in_array($this->publicStatus(), [self::STATUS_CANCELED, self::STATUS_EXPIRED, self::STATUS_UNPAID], true)) {
            return $this->current_period_end;
        }

        return null;
    }

    public function syncTenantActivation(): void
    {
        $tenant = $this->tenant;
        if (! $tenant) {
            return;
        }

        if ($tenant->isAdminSuspended()) {
            if ($tenant->is_active) {
                $tenant->forceFill(['is_active' => false])->save();
            }

            return;
        }

        $shouldBeActive = $this->grantsPlatformAccess();

        if ($tenant->is_active !== $shouldBeActive) {
            $tenant->forceFill(['is_active' => $shouldBeActive])->save();
        }
    }
}
