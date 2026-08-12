<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPrintLog extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'station',
        'driver',
        'status',
        'copies',
        'is_reprint',
        'error_message',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'copies' => 'integer',
            'is_reprint' => 'boolean',
            'printed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
