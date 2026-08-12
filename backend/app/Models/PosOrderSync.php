<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrderSync extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['pending', 'processing', 'synced', 'failed', 'retrying'];

    protected $fillable = [
        'tenant_id',
        'pos_integration_id',
        'order_id',
        'idempotency_key',
        'external_order_id',
        'sync_status',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'last_error',
        'synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'next_retry_at' => 'datetime',
            'synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(PosIntegration::class, 'pos_integration_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PosSyncAttempt::class);
    }

    public function canRetry(): bool
    {
        return in_array($this->sync_status, ['failed', 'retrying'], true)
            && $this->retry_count < $this->max_retries;
    }

    public function isTerminal(): bool
    {
        return in_array($this->sync_status, ['synced', 'failed'], true)
            && ($this->sync_status !== 'failed' || ! $this->canRetry());
    }
}
