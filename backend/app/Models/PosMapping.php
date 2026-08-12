<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosMapping extends Model
{
    use BelongsToTenant;

    public const ENTITY_TYPES = [
        'product',
        'variant_option',
        'addon',
        'category',
        'tax',
        'modifier',
    ];

    protected $fillable = [
        'tenant_id',
        'pos_integration_id',
        'entity_type',
        'local_id',
        'external_id',
        'external_sku',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'local_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(PosIntegration::class, 'pos_integration_id');
    }
}
