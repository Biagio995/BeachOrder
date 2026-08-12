<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Support\LocalizedText;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalizedTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_existing_primary_locale_without_http(): void
    {
        Http::fake();

        [$value, $updated] = LocalizedText::resolve([
            'it' => 'Vegan',
            'el' => 'Vegan EL',
        ], 'el');

        $this->assertSame('Vegan EL', $value);
        $this->assertNull($updated);
        Http::assertNothingSent();
    }

    public function test_prefers_english_source_for_italian_mt(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'Insalata greca'],
            ]),
        ]);

        [$value] = LocalizedText::resolve([
            'el' => 'Χωριάτικη',
            'en' => 'Greek salad',
        ], 'it');

        $this->assertSame('Insalata greca', $value);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'langpair=en%7Cit')
                || str_contains($request->url(), 'langpair=en|it');
        });
    }

    public function test_uses_stored_secondary_when_mt_fails(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => ''],
            ], 500),
        ]);

        [$value] = LocalizedText::resolve([
            'el' => 'Ποτά',
            'en' => 'Drinks',
            'it' => 'Bevande',
        ], 'it');

        $this->assertSame('Bevande', $value);
    }

    public function test_falls_back_to_english_not_greek_for_italian(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => ''],
            ], 500),
        ]);

        [$value] = LocalizedText::resolve([
            'el' => 'Ποτά',
            'en' => 'Drinks',
        ], 'it');

        $this->assertSame('Drinks', $value);
    }

    public function test_rejects_greek_mt_result_for_italian_and_uses_stored(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'Ποτά'],
            ]),
        ]);

        [$value] = LocalizedText::resolve([
            'el' => 'Ποτά',
            'it' => 'Bevande',
        ], 'it');

        $this->assertSame('Bevande', $value);
    }

    public function test_persist_on_model_does_not_store_secondary_locale(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'Spicy'],
            ]),
        ]);

        $tenant = \App\Models\Tenant::query()->create([
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'default_locale' => 'el',
            'is_active' => true,
        ]);
        TenantContext::set($tenant);

        $tag = Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => ['el' => 'Καυτερό', 'en' => 'Spicy'],
            'slug' => 'piccante-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $resolved = LocalizedText::resolveOn($tag, 'name', 'de');
        $tag->refresh();

        $this->assertSame('Spicy', $resolved); // MT from en → de may return "Spicy" from fake for any call
        $this->assertArrayNotHasKey('de', $tag->name ?? []);
        $this->assertSame('Καυτερό', $tag->name['el'] ?? null);
    }

    public function test_apply_resolved_for_response_exposes_current_locale(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'Scharf'],
            ]),
        ]);

        $tenant = \App\Models\Tenant::query()->create([
            'name' => 'T2',
            'slug' => 't2-'.uniqid(),
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'default_locale' => 'el',
            'is_active' => true,
        ]);
        TenantContext::set($tenant);

        $tag = Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => [
                'el' => 'Καυτερό',
                'en' => 'Spicy',
                'it' => 'Piccante',
            ],
            'slug' => 'piccante2-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $resolved = LocalizedText::applyResolvedForResponse($tag, 'name', 'de');

        $this->assertSame('Scharf', $resolved);
        $this->assertSame('Καυτερό', $tag->name['el'] ?? null);
        $this->assertSame('Spicy', $tag->name['en'] ?? null);
        $this->assertSame('Scharf', $tag->name['de'] ?? null);

        $tag->refresh();
        $this->assertArrayNotHasKey('de', $tag->name ?? []);
    }
}
