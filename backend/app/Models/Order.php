<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUSES = [
        'received',
        'accepted',
        'preparing',
        'ready',
        'delivering',
        'delivered',
        'cancelled',
    ];

    public const STATION_STATUSES = [
        'received',
        'accepted',
        'preparing',
        'ready',
    ];

    public const STATIONS = ['kitchen', 'bar'];

    protected $fillable = [
        'tenant_id',
        'order_number',
        'location_id',
        'status',
        'kitchen_status',
        'bar_status',
        'customer_name',
        'customer_session',
        'notes',
        'subtotal',
        'total',
        'payment_method',
        'payment_status',
        'payment_reference',
        'stripe_payment_intent_id',
        'paid_at',
        'payment_error',
        'payment_authorization_code',
        'loyalty_points_earned',
        'accepted_by',
        'delivered_by',
        'accepted_at',
        'ready_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'loyalty_points_earned' => 'integer',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function posSync(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PosOrderSync::class);
    }

    public function stationColumn(string $station): string
    {
        return $station === 'bar' ? 'bar_status' : 'kitchen_status';
    }

    public function stationStatus(string $station): ?string
    {
        return $this->{$this->stationColumn($station)};
    }

    public function activeStations(): array
    {
        $stations = [];
        if ($this->kitchen_status !== null) {
            $stations[] = 'kitchen';
        }
        if ($this->bar_status !== null) {
            $stations[] = 'bar';
        }

        return $stations;
    }

    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            'received' => ['accepted', 'cancelled'],
            'accepted' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['delivering', 'cancelled'],
            'delivering' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
        ];

        return in_array($status, $transitions[$this->status] ?? [], true);
    }

    public function canStationTransitionTo(string $station, string $status): bool
    {
        $current = $this->stationStatus($station);
        if ($current === null) {
            return false;
        }

        $transitions = [
            'received' => ['accepted'],
            'accepted' => ['preparing'],
            'preparing' => ['ready'],
            'ready' => [],
        ];

        return in_array($status, $transitions[$current] ?? [], true);
    }

    public function canRoleUpdateStation(User $user, string $station, string $status): bool
    {
        if (! in_array($station, self::STATIONS, true)) {
            return false;
        }

        if (! $this->canStationTransitionTo($station, $status)) {
            return false;
        }

        if (! $user->hasPermission(\App\Support\RolePermissions::ORDERS_UPDATE_STATUS)) {
            return false;
        }

        if ($user->isAdmin() || $user->isSuperAdmin() || $user->isManager()) {
            return true;
        }

        if ($user->isStaffRole()) {
            return in_array($user->staffPosition(), ['kitchen', 'bar'], true)
                && $user->staffPosition() === $station;
        }

        return false;
    }

    public function canRoleTransitionTo(User $user, string $status): bool
    {
        if (! $this->canTransitionTo($status)) {
            return false;
        }

        if (! $user->hasPermission(\App\Support\RolePermissions::ORDERS_UPDATE_STATUS)) {
            return false;
        }

        if ($user->isAdmin() || $user->isSuperAdmin() || $user->isManager()) {
            return true;
        }

        if ($user->isStaffRole()) {
            if ($user->hasStaffPosition(User::STAFF_POSITION_WAITER)) {
                return in_array($status, [
                    'ready',
                    'delivering',
                    'delivered',
                    'cancelled',
                ], true);
            }

            return false;
        }

        return false;
    }

    public function applyStationStatus(string $station, string $status, ?User $user = null): void
    {
        $column = $this->stationColumn($station);
        $this->{$column} = $status;

        if ($status === 'accepted' && ! $this->accepted_at) {
            $this->accepted_by = $user?->id;
            $this->accepted_at = now();
        }

        $this->syncOverallStatusFromStations();
    }

    public function syncOverallStatusFromStations(): void
    {
        if (in_array($this->status, ['delivering', 'delivered', 'cancelled'], true)) {
            return;
        }

        $statuses = array_values(array_filter([
            $this->kitchen_status,
            $this->bar_status,
        ], fn ($s) => $s !== null));

        if ($statuses === []) {
            return;
        }

        $allReady = collect($statuses)->every(fn ($s) => $s === 'ready');
        if ($allReady) {
            $this->status = 'ready';
            $this->ready_at = $this->ready_at ?? now();

            return;
        }

        // Progress if any station is working; stay below ready until all stations finish.
        if (collect($statuses)->contains('preparing') || collect($statuses)->contains('ready')) {
            $this->status = 'preparing';
        } elseif (collect($statuses)->contains('accepted')) {
            $this->status = 'accepted';
        } else {
            $this->status = 'received';
        }
    }
}
