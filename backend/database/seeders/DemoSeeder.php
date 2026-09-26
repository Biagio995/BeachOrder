<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\ProductVariantGroup;
use App\Models\ProductVariantOption;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Public demo venue for the video campaign.
 *
 * Standalone + idempotent: `php artisan db:seed --class=DemoSeeder`.
 * Creates exactly ONE invented venue (no real business data) with a beach-bar
 * menu in the 4 locales supported by the app (it, en, de, el — stored as JSON
 * maps, exactly like admin-maintained catalog text), QR-coded spots and demo
 * staff users.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $slug = (string) config('demo.tenant_slug', env('DEMO_TENANT_SLUG', 'lido-azzurra'));

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => 'Lido Azzurra',
                'timezone' => 'Europe/Rome',
                'currency' => 'EUR',
                'default_locale' => 'it',
                'branding' => [
                    'primary_color' => '#0E7C7B',
                    'accent_color' => '#F4A259',
                    'tagline' => 'Beach bar · ordina dall’ombrellone',
                ],
                'settings' => Tenant::defaultSettings([
                    'loyalty_enabled' => true,
                    'online_payments_enabled' => false,
                    'country' => 'IT',
                    'nexi' => [
                        'alias' => null,
                        'secret_key' => null,
                        'environment' => 'test',
                    ],
                    'fiscal' => ['enabled' => false],
                    'pos' => ['enabled' => false],
                    'printing' => ['enabled' => false],
                ]),
                'is_active' => true,
                'is_demo' => true,
            ]
        );

        TenantContext::set($tenant);

        try {
            $this->seedUsers($tenant);
            $this->seedLocations($tenant);
            $tags = $this->seedTags($tenant);
            $this->seedMenu($tenant, $tags);
        } finally {
            TenantContext::clear();
        }

        $this->command?->info("Demo venue ready: {$tenant->name} ({$tenant->slug})");
    }

    private function seedUsers(Tenant $tenant): void
    {
        $users = [
            [
                'email' => (string) config('demo.admin_email', 'admin@lido-azzurra.demo'),
                'name' => 'Admin Lido Azzurra',
                'password' => DemoMode::password('DEMO_ADMIN_PASSWORD', 'demo admin'),
                'role' => User::ROLE_ADMIN,
                'staff_position' => null,
            ],
            [
                'email' => (string) config('demo.staff_email', 'cucina@lido-azzurra.demo'),
                'name' => 'Cucina Lido Azzurra',
                'password' => DemoMode::password('DEMO_STAFF_PASSWORD', 'demo kitchen staff'),
                'role' => User::ROLE_STAFF,
                'staff_position' => User::STAFF_POSITION_KITCHEN,
            ],
            [
                'email' => (string) config('demo.waiter_email', 'sala@lido-azzurra.demo'),
                'name' => 'Sala Lido Azzurra',
                'password' => DemoMode::password('DEMO_WAITER_PASSWORD', 'demo waiter'),
                'role' => User::ROLE_STAFF,
                'staff_position' => User::STAFF_POSITION_WAITER,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                    'staff_position' => $user['staff_position'],
                    'is_active' => true,
                ]
            )->markEmailAsVerified();
        }
    }

    private function seedLocations(Tenant $tenant): void
    {
        $spots = [];

        for ($i = 1; $i <= 6; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $spots[] = [
                'name' => "Ombrellone {$n}",
                'slug' => "ombrellone-{$n}",
                'type' => 'umbrella',
                'zone' => 'Spiaggia',
                'capacity' => 2,
            ];
        }

        for ($i = 1; $i <= 2; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $spots[] = [
                'name' => "Tavolo Bar {$n}",
                'slug' => "tavolo-bar-{$n}",
                'type' => 'table',
                'zone' => 'Bar',
                'capacity' => 4,
            ];
        }

        $codes = [];

        foreach ($spots as $spot) {
            $code = Location::stableCode($spot['type'], $spot['name']);
            $codes[] = $code;

            Location::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                $spot + ['tenant_id' => $tenant->id, 'code' => $code, 'is_active' => true]
            );
        }

        // Drop spots that no longer belong to the demo set (stale QR codes).
        Location::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('code', $codes)
            ->delete();
    }

    /**
     * @return array<string, Tag>
     */
    private function seedTags(Tenant $tenant): array
    {
        $defs = [
            'vegetariano' => ['it' => 'Vegetariano', 'en' => 'Vegetarian', 'de' => 'Vegetarisch', 'el' => 'Χορτοφαγικό'],
            'vegano' => ['it' => 'Vegano', 'en' => 'Vegan', 'de' => 'Vegan', 'el' => 'Βίγκαν'],
            'senza-glutine' => ['it' => 'Senza glutine', 'en' => 'Gluten free', 'de' => 'Glutenfrei', 'el' => 'Χωρίς γλουτένη'],
            'piccante' => ['it' => 'Piccante', 'en' => 'Spicy', 'de' => 'Scharf', 'el' => 'Πικάντικο'],
        ];

        $tags = [];
        $order = 1;

        foreach ($defs as $slug => $name) {
            $tags[$slug] = Tag::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'sort_order' => $order++,
                    'is_active' => true,
                ]
            );
        }

        return $tags;
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedMenu(Tenant $tenant, array $tags): void
    {
        $categories = [
            'caffetteria' => [
                'name' => ['it' => 'Caffetteria e colazioni', 'en' => 'Coffee & breakfast', 'de' => 'Kaffee & Frühstück', 'el' => 'Καφές & πρωινό'],
                'description' => ['it' => 'Per iniziare la giornata in spiaggia', 'en' => 'Start your beach day', 'de' => 'Für den Start in den Strandtag', 'el' => 'Για να ξεκινήσει η μέρα στην παραλία'],
                'sort_order' => 1,
            ],
            'panini-piadine' => [
                'name' => ['it' => 'Panini e piadine', 'en' => 'Sandwiches & piadine', 'de' => 'Panini & Piadine', 'el' => 'Πανίνι & πιαντίνες'],
                'description' => ['it' => 'Fatti al momento, pane fresco ogni giorno', 'en' => 'Made fresh daily', 'de' => 'Täglich frisch belegt', 'el' => 'Φρέσκα, φτιαγμένα καθημερινά'],
                'sort_order' => 2,
            ],
            'insalate-poke' => [
                'name' => ['it' => 'Insalate e poké', 'en' => 'Salads & poké', 'de' => 'Salate & Poké', 'el' => 'Σαλάτες & πόκε'],
                'description' => ['it' => 'Fresche, leggere e colorate', 'en' => 'Fresh, light and colorful', 'de' => 'Frisch, leicht und bunt', 'el' => 'Φρέσκες, ελαφριές και πολύχρωμες'],
                'sort_order' => 3,
            ],
            'bibite-cocktail' => [
                'name' => ['it' => 'Bibite e cocktail', 'en' => 'Drinks & cocktails', 'de' => 'Getränke & Cocktails', 'el' => 'Ποτά & κοκτέιλ'],
                'description' => ['it' => 'Ghiacciate, dal bar alla sdraio', 'en' => 'Ice-cold, from bar to sunbed', 'de' => 'Eiskalt, von der Bar an die Liege', 'el' => 'Παγωμένα, από το μπαρ στην ξαπλώστρα'],
                'sort_order' => 4,
            ],
        ];

        $categoryIds = [];

        foreach ($categories as $slug => $def) {
            $categoryIds[$slug] = Category::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                $def + ['tenant_id' => $tenant->id, 'is_active' => true]
            )->id;
        }

        $products = [
            // Caffetteria
            [
                'slug' => 'espresso', 'category' => 'caffetteria', 'station' => 'bar',
                'name' => ['it' => 'Espresso', 'en' => 'Espresso', 'de' => 'Espresso', 'el' => 'Εσπρέσο'],
                'description' => ['it' => 'Miscela della casa, tazzina calda', 'en' => 'House blend', 'de' => 'Hausmischung, heiße Tasse', 'el' => 'Χαρμάνι του σπιτιού, ζεστό φλιτζάνι'],
                'price' => 1.20, 'sort_order' => 1, 'tags' => ['vegano', 'senza-glutine'],
            ],
            [
                'slug' => 'cappuccino', 'category' => 'caffetteria', 'station' => 'bar',
                'name' => ['it' => 'Cappuccino', 'en' => 'Cappuccino', 'de' => 'Cappuccino', 'el' => 'Καπουτσίνο'],
                'description' => ['it' => 'Schiuma densa, spolverata di cacao', 'en' => 'Thick foam, cocoa dust', 'de' => 'Dichter Milchschaum mit Kakao', 'el' => 'Πυκνός αφρός γάλακτος με κακάο'],
                'price' => 1.80, 'sort_order' => 2, 'tags' => ['vegetariano'],
                'addon_groups' => [
                    [
                        'name' => ['it' => 'Latte ed extra', 'en' => 'Milk & extras', 'de' => 'Milch & Extras', 'el' => 'Γάλα & έξτρα'],
                        'addons' => [
                            ['name' => ['it' => 'Latte di soia', 'en' => 'Soy milk', 'de' => 'Sojamilch', 'el' => 'Γάλα σόγιας'], 'price' => 0.50],
                            ['name' => ['it' => 'Panna montata', 'en' => 'Whipped cream', 'de' => 'Schlagsahne', 'el' => 'Σαντιγί'], 'price' => 1.00],
                            ['name' => ['it' => 'Cacao extra', 'en' => 'Extra cocoa', 'de' => 'Extra Kakao', 'el' => 'Έξτρα κακάο'], 'price' => 0.30],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'cornetto', 'category' => 'caffetteria', 'station' => 'bar',
                'name' => ['it' => 'Cornetto artigianale', 'en' => 'Croissant', 'de' => 'Croissant', 'el' => 'Κρουασάν'],
                'description' => ['it' => 'Sfoglia burrosa, sfornato ogni mattina', 'en' => 'Buttery, baked every morning', 'de' => 'Buttrig, jeden Morgen frisch gebacken', 'el' => 'Βουτυρένιο, φρέσκο κάθε πρωί'],
                'price' => 1.60, 'sort_order' => 3, 'tags' => ['vegetariano'],
                'variant_groups' => [
                    [
                        'name' => ['it' => 'Farcitura', 'en' => 'Filling', 'de' => 'Füllung', 'el' => 'Γέμιση'],
                        'is_required' => true,
                        'options' => [
                            ['name' => ['it' => 'Vuoto', 'en' => 'Plain', 'de' => 'Pur', 'el' => 'Σκέτο'], 'price' => 0.00],
                            ['name' => ['it' => 'Crema', 'en' => 'Custard', 'de' => 'Vanillecreme', 'el' => 'Κρέμα'], 'price' => 0.50],
                            ['name' => ['it' => 'Marmellata di albicocche', 'en' => 'Apricot jam', 'de' => 'Aprikosenmarmelade', 'el' => 'Μαρμελάδα βερίκοκο'], 'price' => 0.50],
                            ['name' => ['it' => 'Nutella', 'en' => 'Nutella', 'de' => 'Nutella', 'el' => 'Nutella'], 'price' => 0.70],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'caffe-shakerato', 'category' => 'caffetteria', 'station' => 'bar',
                'name' => ['it' => 'Caffè shakerato', 'en' => 'Iced shaken coffee', 'de' => 'Geshakter Eiskaffee', 'el' => 'Σεϊκαρισμένος καφές'],
                'description' => ['it' => 'Shakerato con ghiaccio, dolce e cremoso', 'en' => 'Shaken with ice', 'de' => 'Mit Eis geshakt, süß und cremig', 'el' => 'Χτυπημένος με πάγο, γλυκός και κρεμώδης'],
                'price' => 3.50, 'sort_order' => 4, 'tags' => ['vegetariano', 'senza-glutine'],
            ],
            // Panini e piadine
            [
                'slug' => 'club-sandwich', 'category' => 'panini-piadine', 'station' => 'kitchen',
                'name' => ['it' => 'Club sandwich', 'en' => 'Club sandwich', 'de' => 'Clubsandwich', 'el' => 'Κλαμπ σάντουιτς'],
                'description' => ['it' => 'Pollo grigliato, lattuga, pomodoro, maionese', 'en' => 'Grilled chicken, lettuce, tomato, mayo', 'de' => 'Gegrilltes Hähnchen, Salat, Tomate, Mayo', 'el' => 'Ψητό κοτόπουλο, μαρούλι, ντομάτα, μαγιονέζα'],
                'price' => 9.50, 'sort_order' => 1, 'prep_time_minutes' => 12,
                'allergens' => ['gluten', 'eggs'],
                'addon_groups' => [
                    [
                        'name' => ['it' => 'Extra', 'en' => 'Extras', 'de' => 'Extras', 'el' => 'Έξτρα'],
                        'addons' => [
                            ['name' => ['it' => 'Patatine fritte', 'en' => 'French fries', 'de' => 'Pommes frites', 'el' => 'Τηγανητές πατάτες'], 'price' => 2.50],
                            ['name' => ['it' => 'Bacon croccante', 'en' => 'Crispy bacon', 'de' => 'Knuspriger Bacon', 'el' => 'Τραγανό μπέικον'], 'price' => 2.00],
                            ['name' => ['it' => 'Salsa cocktail', 'en' => 'Cocktail sauce', 'de' => 'Cocktailsauce', 'el' => 'Σος κοκτέιλ'], 'price' => 0.50],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'piadina-crudo-squacquerone', 'category' => 'panini-piadine', 'station' => 'kitchen',
                'name' => ['it' => 'Piadina crudo e squacquerone', 'en' => 'Parma ham & squacquerone piadina', 'de' => 'Piadina mit Parmaschinken', 'el' => 'Πιαντίνα με προσούτο'],
                'description' => ['it' => 'Prosciutto crudo 18 mesi, squacquerone, rucola', 'en' => 'Parma ham, soft cheese, rocket', 'de' => '18 Monate Parmaschinken, Squacquerone, Rucola', 'el' => 'Προσούτο 18 μηνών, σκουακουερόνε, ρόκα'],
                'price' => 7.00, 'sort_order' => 2, 'prep_time_minutes' => 8,
                'allergens' => ['gluten', 'dairy'],
            ],
            [
                'slug' => 'panino-caprese', 'category' => 'panini-piadine', 'station' => 'kitchen',
                'name' => ['it' => 'Panino caprese', 'en' => 'Caprese sandwich', 'de' => 'Caprese-Sandwich', 'el' => 'Πανίνι καπρέζε'],
                'description' => ['it' => 'Mozzarella di bufala, pomodoro, basilico', 'en' => 'Buffalo mozzarella, tomato, basil', 'de' => 'Büffelmozzarella, Tomate, Basilikum', 'el' => 'Μοτσαρέλα βουβαλίσια, ντομάτα, βασιλικός'],
                'price' => 6.50, 'sort_order' => 3, 'prep_time_minutes' => 6,
                'allergens' => ['gluten', 'dairy'], 'tags' => ['vegetariano'],
                'variant_groups' => [
                    [
                        'name' => ['it' => 'Pane', 'en' => 'Bread', 'de' => 'Brot', 'el' => 'Ψωμί'],
                        'is_required' => true,
                        'options' => [
                            ['name' => ['it' => 'Classico', 'en' => 'Classic', 'de' => 'Klassisch', 'el' => 'Κλασικό'], 'price' => 0.00],
                            ['name' => ['it' => 'Integrale', 'en' => 'Wholegrain', 'de' => 'Vollkorn', 'el' => 'Ολικής'], 'price' => 0.50],
                            ['name' => ['it' => 'Senza glutine', 'en' => 'Gluten free', 'de' => 'Glutenfrei', 'el' => 'Χωρίς γλουτένη'], 'price' => 1.00],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'toast-farcito', 'category' => 'panini-piadine', 'station' => 'kitchen',
                'name' => ['it' => 'Toast farcito', 'en' => 'Toasted sandwich', 'de' => 'Überbackener Toast', 'el' => 'Τοστ'],
                'description' => ['it' => 'Prosciutto cotto e fontina, tostato', 'en' => 'Ham & cheese, toasted', 'de' => 'Kochschinken und Käse, getoastet', 'el' => 'Ζαμπόν και τυρί, ψημένο'],
                'price' => 4.50, 'sort_order' => 4, 'prep_time_minutes' => 5,
                'allergens' => ['gluten', 'dairy'],
            ],
            // Insalate e poké
            [
                'slug' => 'insalata-greca', 'category' => 'insalate-poke', 'station' => 'kitchen',
                'name' => ['it' => 'Insalata greca', 'en' => 'Greek salad', 'de' => 'Griechischer Salat', 'el' => 'Χωριάτικη σαλάτα'],
                'description' => ['it' => 'Feta, olive taggiasche, cetrioli, cipolla rossa', 'en' => 'Feta, olives, cucumber, red onion', 'de' => 'Feta, Taggiasca-Oliven, Gurke, rote Zwiebel', 'el' => 'Φέτα, ελιές Τάτζιασκες, αγγούρι, κόκκινο κρεμμύδι'],
                'price' => 8.00, 'sort_order' => 1, 'prep_time_minutes' => 8,
                'allergens' => ['dairy'], 'tags' => ['vegetariano', 'senza-glutine'],
                'addon_groups' => [
                    [
                        'name' => ['it' => 'Aggiunte', 'en' => 'Add-ons', 'de' => 'Zusätze', 'el' => 'Προσθήκες'],
                        'addons' => [
                            ['name' => ['it' => 'Feta extra', 'en' => 'Extra feta', 'de' => 'Extra Feta', 'el' => 'Έξτρα φέτα'], 'price' => 1.50],
                            ['name' => ['it' => 'Avocado', 'en' => 'Avocado', 'de' => 'Avocado', 'el' => 'Αβοκάντο'], 'price' => 2.00],
                            ['name' => ['it' => 'Olive taggiasche', 'en' => 'Taggiasche olives', 'de' => 'Taggiasca-Oliven', 'el' => 'Ελιές Τάτζιασκες'], 'price' => 1.00],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'poke-salmone', 'category' => 'insalate-poke', 'station' => 'kitchen',
                'name' => ['it' => 'Poké al salmone', 'en' => 'Salmon poké', 'de' => 'Lachs-Poké', 'el' => 'Πόκε με σολωμό'],
                'description' => ['it' => 'Salmone marinato, edamame, mango, salsa ponzu', 'en' => 'Marinated salmon, edamame, mango, ponzu', 'de' => 'Marinierter Lachs, Edamame, Mango, Ponzu', 'el' => 'Μαριναρισμένος σολωμός, ενταμάμε, μάνγκο, πόνζου'],
                'price' => 12.00, 'sort_order' => 2, 'prep_time_minutes' => 10,
                'allergens' => ['fish', 'soy'], 'tags' => ['senza-glutine'],
                'variant_groups' => [
                    [
                        'name' => ['it' => 'Base', 'en' => 'Base', 'de' => 'Basis', 'el' => 'Βάση'],
                        'is_required' => true,
                        'options' => [
                            ['name' => ['it' => 'Riso bianco', 'en' => 'White rice', 'de' => 'Weißer Reis', 'el' => 'Λευκό ρύζι'], 'price' => 0.00],
                            ['name' => ['it' => 'Riso venere', 'en' => 'Black rice', 'de' => 'Schwarzer Reis', 'el' => 'Μαύρο ρύζι'], 'price' => 1.00],
                            ['name' => ['it' => 'Insalata verde', 'en' => 'Green salad', 'de' => 'Grüner Salat', 'el' => 'Πράσινη σαλάτα'], 'price' => 0.00],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'caesar-pollo', 'category' => 'insalate-poke', 'station' => 'kitchen',
                'name' => ['it' => 'Caesar con pollo', 'en' => 'Chicken caesar', 'de' => 'Caesar Salad mit Hähnchen', 'el' => 'Σίζαρ με κοτόπουλο'],
                'description' => ['it' => 'Pollo grigliato, crostini, scaglie di grana', 'en' => 'Grilled chicken, croutons, parmesan', 'de' => 'Gegrilltes Hähnchen, Croûtons, Parmesan', 'el' => 'Ψητό κοτόπουλο, κρουτόν, παρμεζάνα'],
                'price' => 9.00, 'sort_order' => 3, 'prep_time_minutes' => 10,
                'allergens' => ['gluten', 'eggs', 'dairy'],
            ],
            // Bibite e cocktail
            [
                'slug' => 'acqua-naturale', 'category' => 'bibite-cocktail', 'station' => 'bar',
                'name' => ['it' => 'Acqua naturale', 'en' => 'Still water', 'de' => 'Stilles Wasser', 'el' => 'Φυσικό νερό'],
                'description' => ['it' => 'Bottiglia 50cl, servita fresca', 'en' => '50cl bottle, served chilled', 'de' => '50-cl-Flasche, gekühlt serviert', 'el' => 'Μπουκάλι 50cl, σερβιρισμένο δροσερό'],
                'price' => 2.00, 'sort_order' => 1, 'tags' => ['vegano', 'senza-glutine'],
            ],
            [
                'slug' => 'coca-cola', 'category' => 'bibite-cocktail', 'station' => 'bar',
                'name' => ['it' => 'Coca-Cola', 'en' => 'Coca-Cola', 'de' => 'Coca-Cola', 'el' => 'Coca-Cola'],
                'description' => ['it' => 'Bottiglia in vetro 33cl con ghiaccio e limone', 'en' => '33cl glass bottle with ice & lemon', 'de' => '33-cl-Glasflasche mit Eis und Zitrone', 'el' => 'Γυάλινο μπουκάλι 33cl με πάγο και λεμόνι'],
                'price' => 3.50, 'sort_order' => 2,
                'track_inventory' => true, 'stock_quantity' => 48, 'low_stock_threshold' => 8,
            ],
            [
                'slug' => 'spritz-aperol', 'category' => 'bibite-cocktail', 'station' => 'bar',
                'name' => ['it' => 'Spritz Aperol', 'en' => 'Aperol spritz', 'de' => 'Aperol Spritz', 'el' => 'Σπριτς Απερόλ'],
                'description' => ['it' => 'Aperol, prosecco, soda, fetta d’arancia', 'en' => 'Aperol, prosecco, soda, orange', 'de' => 'Aperol, Prosecco, Soda, Orangenscheibe', 'el' => 'Απερόλ, prosecco, σόδα, φέτα πορτοκάλι'],
                'price' => 7.00, 'sort_order' => 3, 'prep_time_minutes' => 4,
                'allergens' => ['sulfites'], 'tags' => ['vegano', 'senza-glutine'],
                'variant_groups' => [
                    [
                        'name' => ['it' => 'Formato', 'en' => 'Size', 'de' => 'Größe', 'el' => 'Μέγεθος'],
                        'is_required' => true,
                        'options' => [
                            ['name' => ['it' => 'Classico', 'en' => 'Classic', 'de' => 'Klassisch', 'el' => 'Κλασικό'], 'price' => 0.00],
                            ['name' => ['it' => 'Grande', 'en' => 'Large', 'de' => 'Groß', 'el' => 'Μεγάλο'], 'price' => 2.00],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'mojito-analcolico', 'category' => 'bibite-cocktail', 'station' => 'bar',
                'name' => ['it' => 'Mojito analcolico', 'en' => 'Virgin mojito', 'de' => 'Virgin Mojito', 'el' => 'Μοχίτο χωρίς αλκοόλ'],
                'description' => ['it' => 'Menta fresca, lime, soda, zucchero di canna', 'en' => 'Fresh mint, lime, soda', 'de' => 'Frische Minze, Limette, Soda', 'el' => 'Φρέσκος δυόσμος, λάιμ, σόδα'],
                'price' => 6.00, 'sort_order' => 4, 'prep_time_minutes' => 5,
                'tags' => ['vegano', 'senza-glutine'],
                'addon_groups' => [
                    [
                        'name' => ['it' => 'Extra', 'en' => 'Extras', 'de' => 'Extras', 'el' => 'Έξτρα'],
                        'addons' => [
                            ['name' => ['it' => 'Menta extra', 'en' => 'Extra mint', 'de' => 'Extra Minze', 'el' => 'Έξτρα δυόσμος'], 'price' => 0.50],
                            ['name' => ['it' => 'Zenzero fresco', 'en' => 'Fresh ginger', 'de' => 'Frischer Ingwer', 'el' => 'Φρέσκο τζίντζερ'], 'price' => 0.50],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'birra-artigianale', 'category' => 'bibite-cocktail', 'station' => 'bar',
                'name' => ['it' => 'Birra artigianale', 'en' => 'Craft beer', 'de' => 'Craft-Bier', 'el' => 'Χειροποίητη μπύρα'],
                'description' => ['it' => 'Blanche 33cl del birrificio locale', 'en' => '33cl blanche from the local brewery', 'de' => 'Blanche 33 cl von der lokalen Brauerei', 'el' => 'Blanche 33cl από τοπική ζυθοποιία'],
                'price' => 5.50, 'sort_order' => 5,
                'allergens' => ['gluten'],
                'track_inventory' => true, 'stock_quantity' => 36, 'low_stock_threshold' => 6,
            ],
        ];

        foreach ($products as $def) {
            $product = Product::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $def['slug']],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categoryIds[$def['category']],
                    'station' => $def['station'],
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'price' => $def['price'],
                    'allergens' => $def['allergens'] ?? [],
                    'is_available' => true,
                    'is_active' => true,
                    'sort_order' => $def['sort_order'],
                    'prep_time_minutes' => $def['prep_time_minutes'] ?? null,
                    'track_inventory' => $def['track_inventory'] ?? false,
                    'stock_quantity' => $def['stock_quantity'] ?? null,
                    'low_stock_threshold' => $def['low_stock_threshold'] ?? null,
                ]
            );

            $tagIds = [];

            foreach ($def['tags'] ?? [] as $tagSlug) {
                if (isset($tags[$tagSlug])) {
                    $tagIds[] = $tags[$tagSlug]->id;
                }
            }

            $product->tags()->sync($tagIds);

            $this->syncVariants($tenant, $product, $def['variant_groups'] ?? []);
            $this->syncAddons($tenant, $product, $def['addon_groups'] ?? []);
        }
    }

    /**
     * Rebuild customization groups for a demo product. Order items store
     * snapshots (not FKs), so rebuilding keeps the seeder idempotent without
     * orphaning historical orders.
     *
     * @param  list<array{name: array<string,string>, is_required?: bool, options: list<array{name: array<string,string>, price: float}>}>  $groups
     */
    private function syncVariants(Tenant $tenant, Product $product, array $groups): void
    {
        ProductVariantOption::query()->where('tenant_id', $tenant->id)->whereIn(
            'product_variant_group_id',
            ProductVariantGroup::query()->where('tenant_id', $tenant->id)->where('product_id', $product->id)->pluck('id')
        )->delete();

        ProductVariantGroup::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $product->id)
            ->delete();

        $order = 1;

        foreach ($groups as $group) {
            $created = ProductVariantGroup::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'name' => $group['name'],
                'is_required' => (bool) ($group['is_required'] ?? false),
                'is_active' => true,
                'sort_order' => $order++,
            ]);

            $optionOrder = 1;

            foreach ($group['options'] as $option) {
                ProductVariantOption::query()->create([
                    'tenant_id' => $tenant->id,
                    'product_variant_group_id' => $created->id,
                    'name' => $option['name'],
                    'price' => $option['price'] ?? 0,
                    'is_active' => true,
                    'sort_order' => $optionOrder++,
                ]);
            }
        }
    }

    /**
     * @param  list<array{name: array<string,string>, addons: list<array{name: array<string,string>, price: float}>}>  $groups
     */
    private function syncAddons(Tenant $tenant, Product $product, array $groups): void
    {
        ProductAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $product->id)
            ->delete();

        ProductAddonGroup::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $product->id)
            ->delete();

        $order = 1;

        foreach ($groups as $group) {
            $created = ProductAddonGroup::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'name' => $group['name'],
                'min_selections' => 0,
                'max_selections' => null,
                'is_active' => true,
                'sort_order' => $order++,
            ]);

            $addonOrder = 1;

            foreach ($group['addons'] as $addon) {
                ProductAddon::query()->create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'product_addon_group_id' => $created->id,
                    'name' => $addon['name'],
                    'price' => $addon['price'] ?? 0,
                    'is_active' => true,
                    'min_quantity' => 0,
                    'max_quantity' => 3,
                    'sort_order' => $addonOrder++,
                ]);
            }
        }
    }
}
