<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'super@beachorder.test')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Platform Super Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'role' => User::ROLE_SUPER_ADMIN,
                'tenant_id' => null,
                'is_active' => true,
            ]
        )->markEmailAsVerified();

        $azure = Tenant::query()->updateOrCreate(
            ['slug' => 'azure-beach'],
            [
                'name' => 'Azure Beach',
                'timezone' => 'Europe/Rome',
                'currency' => 'EUR',
                'default_locale' => 'it',
                'branding' => [
                    'primary_color' => '#0B6E6B',
                    'accent_color' => '#E07A5F',
                    'tagline' => 'Ordina dall\'ombrellone',
                ],
                'settings' => Tenant::defaultSettings([
                    'loyalty_enabled' => true,
                    'online_payments_enabled' => true,
                    'country' => 'IT',
                ]),
                'is_active' => true,
                'is_demo' => true,
            ]
        );

        $sunset = Tenant::query()->updateOrCreate(
            ['slug' => 'sunset-lido'],
            [
                'name' => 'Sunset Lido',
                'timezone' => 'Europe/Athens',
                'currency' => 'EUR',
                'default_locale' => 'el',
                'branding' => [
                    'primary_color' => '#C45C26',
                    'accent_color' => '#1B6CA8',
                    'tagline' => 'Παράγγειλε από την ομπρέλα',
                ],
                'settings' => Tenant::defaultSettings([
                    'loyalty_enabled' => true,
                    'online_payments_enabled' => true,
                    'country' => 'GR',
                ]),
                'is_active' => true,
                'is_demo' => true,
            ]
        );

        $this->seedTenant($azure, [
            'admin' => ['Admin Azure', 'admin@azure.test'],
            'manager' => ['Manager Azure', 'manager@azure.test'],
            'staff' => ['Staff Azure', 'staff@azure.test'],
            'menu_premium' => false,
        ]);

        $this->seedTenant($sunset, [
            'admin' => ['Admin Sunset', 'admin@sunset.test'],
            'manager' => ['Manager Sunset', 'manager@sunset.test'],
            'staff' => ['Staff Sunset', 'staff@sunset.test'],
            'menu_premium' => true,
        ]);

        TenantContext::clear();
    }

    private function seedTenant(Tenant $tenant, array $cfg): void
    {
        TenantContext::set($tenant);

        foreach ([
            ['email' => $cfg['admin'][1], 'name' => $cfg['admin'][0], 'role' => User::ROLE_ADMIN],
            ['email' => $cfg['manager'][1], 'name' => $cfg['manager'][0], 'role' => User::ROLE_MANAGER],
            ['email' => $cfg['staff'][1], 'name' => $cfg['staff'][0], 'role' => User::ROLE_STAFF],
        ] as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'is_active' => true,
                ]
            )->markEmailAsVerified();
        }

        foreach ([
            ['name' => 'Ombrellone 12', 'slug' => 'ombrellone-12', 'type' => 'umbrella', 'code' => 'umbrella12', 'zone' => 'A', 'capacity' => 4],
            ['name' => 'Ombrellone 18', 'slug' => 'ombrellone-18', 'type' => 'umbrella', 'code' => 'umbrella18', 'zone' => 'A', 'capacity' => 4],
            ['name' => 'Lettino 5', 'slug' => 'lettino-5', 'type' => 'sunbed', 'code' => 'sunbed5', 'zone' => 'B', 'capacity' => 1],
            ['name' => 'Tavolo 3', 'slug' => 'tavolo-3', 'type' => 'table', 'code' => 'table3', 'zone' => 'Bar', 'capacity' => 6],
        ] as $loc) {
            Location::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $loc['code']],
                $loc + ['tenant_id' => $tenant->id, 'is_active' => true]
            );
        }

        $drinks = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'bevande'],
            [
                'tenant_id' => $tenant->id,
                'name' => ['it' => 'Bevande', 'en' => 'Drinks', 'el' => 'Ποτά', 'de' => 'Getränke'],
                'description' => ['it' => 'Fresche e ghiacciate', 'en' => 'Cold and refreshing', 'el' => 'Δροσερά ποτά', 'de' => 'Frisch und eiskalt'],
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $food = Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'cibo'],
            [
                'tenant_id' => $tenant->id,
                'name' => ['it' => 'Cibo', 'en' => 'Food', 'el' => 'Φαγητό', 'de' => 'Essen'],
                'description' => ['it' => 'Snack e piatti leggeri', 'en' => 'Snacks and light meals', 'el' => 'Σνακ', 'de' => 'Snacks'],
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $tagDefs = [
            ['slug' => 'vegan', 'sort_order' => 1, 'name' => ['it' => 'Vegan', 'en' => 'Vegan', 'el' => 'Vegan', 'de' => 'Vegan']],
            ['slug' => 'vegetariano', 'sort_order' => 2, 'name' => ['it' => 'Vegetariano', 'en' => 'Vegetarian', 'el' => 'Χορτοφαγικό', 'de' => 'Vegetarisch']],
            ['slug' => 'senza-glutine', 'sort_order' => 3, 'name' => ['it' => 'Senza glutine', 'en' => 'Gluten free', 'el' => 'Χωρίς γλουτένη', 'de' => 'Glutenfrei']],
            ['slug' => 'senza-lattosio', 'sort_order' => 4, 'name' => ['it' => 'Senza lattosio', 'en' => 'Lactose free', 'el' => 'Χωρίς λακτόζη', 'de' => 'Laktosefrei']],
            ['slug' => 'piccante', 'sort_order' => 5, 'name' => ['it' => 'Piccante', 'en' => 'Spicy', 'el' => 'Πικάντικο', 'de' => 'Scharf']],
        ];

        $tagsBySlug = [];
        foreach ($tagDefs as $tagDef) {
            $tagsBySlug[$tagDef['slug']] = Tag::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $tagDef['slug']],
                $tagDef + ['tenant_id' => $tenant->id, 'is_active' => true]
            );
        }

        $products = [
            [
                'category_id' => $drinks->id,
                'station' => 'bar',
                'slug' => 'acqua-naturale',
                'name' => ['it' => 'Acqua naturale', 'en' => 'Still water', 'el' => 'Νερό', 'de' => 'Wasser still'],
                'description' => ['it' => 'Bottiglia 50cl', 'en' => '50cl bottle', 'el' => 'Μπουκάλι 50cl', 'de' => 'Flasche 50cl'],
                'price' => $cfg['menu_premium'] ? 3.00 : 2.50,
                'allergens' => [],
                'sort_order' => 1,
            ],
            [
                'category_id' => $drinks->id,
                'station' => 'bar',
                'slug' => 'coca-cola',
                'name' => ['it' => 'Coca-Cola', 'en' => 'Coca-Cola', 'el' => 'Coca-Cola', 'de' => 'Coca-Cola'],
                'description' => ['it' => 'Lattina 33cl', 'en' => '33cl can', 'el' => 'Κουτάκι 33cl', 'de' => 'Dose 33cl'],
                'price' => $cfg['menu_premium'] ? 4.00 : 3.50,
                'allergens' => [],
                'sort_order' => 2,
                'track_inventory' => true,
                'stock_quantity' => 24,
                'low_stock_threshold' => 5,
            ],
            [
                'category_id' => $food->id,
                'station' => 'kitchen',
                'slug' => 'club-sandwich',
                'name' => ['it' => 'Club sandwich', 'en' => 'Club sandwich', 'el' => 'Club sandwich', 'de' => 'Clubsandwich'],
                'description' => ['it' => 'Pollo, lattuga, pomodoro', 'en' => 'Chicken, lettuce, tomato', 'el' => 'Κοτόπουλο', 'de' => 'Hähnchen'],
                'price' => $cfg['menu_premium'] ? 11.00 : 9.50,
                'allergens' => ['gluten', 'eggs'],
                'sort_order' => 1,
                'prep_time_minutes' => 12,
                'track_inventory' => true,
                'stock_quantity' => 15,
                'low_stock_threshold' => 3,
            ],
            [
                'category_id' => $food->id,
                'station' => 'kitchen',
                'slug' => 'insalata-greca',
                'name' => ['it' => 'Insalata greca', 'en' => 'Greek salad', 'el' => 'Χωριάτικη', 'de' => 'Griechischer Salat'],
                'description' => ['it' => 'Feta, olive, cetrioli', 'en' => 'Feta, olives, cucumber', 'el' => 'Φέτα, ελιές', 'de' => 'Feta, Oliven'],
                'price' => $cfg['menu_premium'] ? 9.50 : 8.00,
                'allergens' => ['dairy'],
                'sort_order' => 2,
                'prep_time_minutes' => 8,
            ],
        ];

        if ($cfg['menu_premium']) {
            $products[] = [
                'category_id' => $drinks->id,
                'station' => 'bar',
                'slug' => 'mojito',
                'name' => ['it' => 'Mojito', 'en' => 'Mojito', 'el' => 'Mojito', 'de' => 'Mojito'],
                'description' => ['it' => 'Rum, menta, lime', 'en' => 'Rum, mint, lime', 'el' => 'Ρούμι, μέντα', 'de' => 'Rum, Minze'],
                'price' => 10.00,
                'allergens' => [],
                'sort_order' => 3,
                'prep_time_minutes' => 5,
            ];
        }

        foreach ($products as $product) {
            $created = Product::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $product['slug']],
                $product + [
                    'tenant_id' => $tenant->id,
                    'is_available' => true,
                    'is_active' => true,
                ]
            );

            $tagSlugs = match ($product['slug']) {
                'insalata-greca' => ['vegetariano', 'senza-glutine'],
                'acqua-naturale' => ['vegan', 'senza-glutine', 'senza-lattosio'],
                'mojito' => ['vegan', 'senza-glutine'],
                default => [],
            };

            if ($tagSlugs !== []) {
                $created->tags()->sync(
                    collect($tagSlugs)->map(fn (string $slug) => $tagsBySlug[$slug]->id)->all()
                );
            }
        }
    }
}
