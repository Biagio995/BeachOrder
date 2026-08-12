<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    /** @var list<string> */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'app', 'login', 'logout', 'register', 'kitchen', 'bar', 'waiter',
        't', 'q', 'cart', 'menu', 'www', 'mail', 'support', 'help', 'status',
    ];

    /** @var list<string> */
    public const FISCAL_PROVIDERS = ['epson_epos', 'custom', 'rch', 'mydata', 'other'];

    /** @var list<string> */
    public const FISCAL_MODES = ['local_bridge', 'cloud_api'];

    /** @var list<string> */
    public const POS_PROVIDERS = ['nexi', 'sumup', 'axerve', 'other'];

    /** @var list<string> */
    public const PRINT_DRIVERS = ['escpos_tcp', 'printnode', 'local_bridge'];

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'currency',
        'default_locale',
        'branding',
        'settings',
        'is_active',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    /**
     * Default per-tenant settings (fiscal POS + kitchen printing).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function defaultSettings(array $overrides = []): array
    {
        return array_replace_recursive([
            'loyalty_enabled' => true,
            'online_payments_enabled' => true,
            'country' => 'IT',
            'fiscal' => [
                'enabled' => false,
                'provider' => null,
                'mode' => 'local_bridge',
                'device_id' => null,
                'credentials_ref' => null,
                'auto_fiscalize_on_pay' => true,
            ],
            'pos' => [
                'enabled' => false,
                'provider' => null,
                'terminal_id' => null,
                'credentials_ref' => null,
            ],
            'printing' => [
                'enabled' => false,
                'driver' => 'escpos_tcp',
                'credentials_ref' => null,
                'stations' => [
                    'kitchen' => [
                        'host' => null,
                        'port' => 9100,
                        'copies' => 1,
                    ],
                    'bar' => [
                        'host' => null,
                        'port' => 9100,
                        'copies' => 1,
                    ],
                ],
            ],
        ], $overrides);
    }

    /**
     * Deep-merge incoming settings onto current ones (never wipe sibling keys).
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeSettings(array $incoming): array
    {
        return array_replace_recursive($this->settings ?? [], $incoming);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function posIntegration(): HasOne
    {
        return $this->hasOne(PosIntegration::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription?->grantsPlatformAccess() ?? false;
    }

    public function isAdminSuspended(): bool
    {
        return (bool) $this->setting('admin_suspended', false);
    }

    public function revokeAllStaffTokens(): void
    {
        $this->users()->each(fn (User $user) => $user->revokeAllTokens());
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function loyaltyEnabled(): bool
    {
        return (bool) $this->setting('loyalty_enabled', true);
    }

    public function onlinePaymentsEnabled(): bool
    {
        return (bool) $this->setting('online_payments_enabled', true);
    }

    public function country(): string
    {
        return strtoupper((string) $this->setting('country', 'IT'));
    }

    public function fiscalEnabled(): bool
    {
        return (bool) $this->setting('fiscal.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function fiscalConfig(): array
    {
        return (array) $this->setting('fiscal', []);
    }

    public function posEnabled(): bool
    {
        return (bool) $this->setting('pos.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function posConfig(): array
    {
        return (array) $this->setting('pos', []);
    }

    public function printingEnabled(): bool
    {
        return (bool) $this->setting('printing.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function printingConfig(): array
    {
        return (array) $this->setting('printing', []);
    }

    /**
     * Build a unique URL slug from the company name (ragione sociale).
     */
    public static function uniqueSlugFromName(string $name, ?string $preferred = null): string
    {
        $base = Str::slug($preferred ?: $name) ?: 'tenant';
        $base = Str::limit($base, 70, '');

        if (in_array($base, self::RESERVED_SLUGS, true)) {
            $base .= '-bar';
        }

        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 70, '').'-'.$i;
            $i++;
        }

        return $slug;
    }
}
