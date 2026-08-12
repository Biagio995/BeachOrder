<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSyncAttempt extends Model
{
    protected $fillable = [
        'pos_order_sync_id',
        'attempt_number',
        'status',
        'error_message',
        'response_summary',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'response_summary' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function orderSync(): BelongsTo
    {
        return $this->belongsTo(PosOrderSync::class, 'pos_order_sync_id');
    }
}
