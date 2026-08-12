<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Location extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'type',
        'code',
        'zone',
        'capacity',
        'is_active',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
            'capacity' => 'integer',
        ];
    }

    /**
     * Stable QR slug: {type}{number} e.g. umbrella12, sunbed5, table3.
     * Number is the last digit sequence in the location name.
     */
    public static function stableCode(string $type, string $name): string
    {
        if (! preg_match_all('/\d+/', $name, $matches) || empty($matches[0])) {
            throw new InvalidArgumentException('Location name must contain a number (e.g. "Ombrellone 12").');
        }

        $number = end($matches[0]);

        return Str::lower($type) . $number;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(LocationAccessToken::class);
    }
}
