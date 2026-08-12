<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosIntegration extends Model
{
    use BelongsToTenant;

    public const PROVIDERS = ['epsilon_pylon', 'softone', 'custom'];

    public const CONNECTION_STATUSES = ['disconnected', 'connected', 'error'];

    protected $fillable = [
        'tenant_id',
        'provider',
        'api_endpoint',
        'credentials',
        'store_location_id',
        'extra_params',
        'is_enabled',
        'connection_status',
        'last_sync_at',
        'last_connection_test_at',
        'last_connection_error',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'extra_params' => 'array',
            'is_enabled' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_connection_test_at' => 'datetime',
        ];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(PosMapping::class);
    }

    public function orderSyncs(): HasMany
    {
        return $this->hasMany(PosOrderSync::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOperational(): bool
    {
        return $this->is_enabled
            && $this->connection_status === 'connected';
    }
}
