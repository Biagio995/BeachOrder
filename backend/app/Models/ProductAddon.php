<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAddon extends Model
{
    use BelongsToTenant, HasFactory, HasTranslatedName;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_addon_group_id',
        'name',
        'price',
        'is_active',
        'min_quantity',
        'max_quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductAddonGroup::class, 'product_addon_group_id');
    }

}
