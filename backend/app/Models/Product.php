<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslatedName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant, HasFactory, HasTranslatedName;

    public const STATIONS = ['kitchen', 'bar'];

    protected $fillable = [
        'tenant_id',
        'category_id',
        'station',
        'name',
        'description',
        'slug',
        'price',
        'image_path',
        'allergens',
        'is_available',
        'is_active',
        'sort_order',
        'prep_time_minutes',
        'stock_quantity',
        'track_inventory',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'allergens' => 'array',
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'prep_time_minutes' => 'integer',
            'stock_quantity' => 'integer',
            'track_inventory' => 'boolean',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('tags.sort_order');
    }

    public function variantGroups(): HasMany
    {
        return $this->hasMany(ProductVariantGroup::class)->orderBy('sort_order');
    }

    public function addonGroups(): HasMany
    {
        return $this->hasMany(ProductAddonGroup::class)->orderBy('sort_order');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ProductAddon::class)->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function imageUrl(): ?string
    {
        return \App\Support\PublicUploadUrl::fromPath($this->image_path);
    }
}
