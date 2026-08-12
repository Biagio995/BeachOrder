<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\ProductVariantGroup;
use App\Models\ProductVariantOption;
use App\Models\Tag;
use App\Models\Tenant;
use App\Support\LocalizedText;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function tenantStatus(string $tenant): JsonResponse
    {
        $record = Tenant::query()->where('slug', $tenant)->first();

        if (! $record) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        return response()->json([
            'is_active' => $record->is_active,
            'admin_suspended' => $record->isAdminSuspended(),
            'tenant' => $this->publicTenantPayload($record),
        ]);
    }

    public function tenantInfo(): JsonResponse
    {
        $tenant = TenantContext::get();

        return response()->json(array_merge($this->publicTenantPayload($tenant), [
            'timezone' => $tenant->timezone,
        ]));
    }

    public function locationByCode(string $code): JsonResponse
    {
        $location = Location::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($location);
    }

    public function categories(Request $request): JsonResponse
    {
        $locale = $request->query('locale', TenantContext::get()?->default_locale ?? 'it');

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with($this->productWith())
            ->get()
            ->map(fn (Category $category) => $this->transformCategory($category, $locale));

        return response()->json($categories);
    }

    public function menu(Request $request): JsonResponse
    {
        $locale = $request->query('locale', TenantContext::get()?->default_locale ?? 'it');
        $code = $request->query('code');

        $location = null;
        if ($code) {
            $location = Location::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->firstOrFail();
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with($this->productWith())
            ->get()
            ->map(fn (Category $category) => $this->transformCategory($category, $locale));

        $tenant = TenantContext::get();

        return response()->json([
            'tenant' => $this->publicTenantPayload($tenant),
            'location' => $location,
            'categories' => $categories,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicTenantPayload(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'restaurant_name' => $tenant->name,
            'slug' => $tenant->slug,
            'branding' => TenantBranding::resolve($tenant->branding),
            'currency' => $tenant->currency,
            'default_locale' => $tenant->default_locale,
            'settings' => [
                'online_payments_enabled' => $tenant->onlinePaymentsEnabled(),
            ],
        ];
    }

    private function productWith(): array
    {
        return [
            'products' => fn ($q) => $q
                ->where('is_active', true)
                ->where('is_available', true)
                ->orderBy('sort_order')
                ->with([
                    'tags' => fn ($tq) => $tq->where('is_active', true)->orderBy('sort_order'),
                    'variantGroups' => fn ($vq) => $vq->where('is_active', true)->orderBy('sort_order')
                        ->with(['options' => fn ($oq) => $oq->where('is_active', true)->orderBy('sort_order')]),
                    'addonGroups' => fn ($aq) => $aq->where('is_active', true)->orderBy('sort_order')
                        ->with(['addons' => fn ($adq) => $adq->where('is_active', true)->orderBy('sort_order')]),
                ]),
        ];
    }

    private function transformCategory(Category $category, string $locale): array
    {
        $locale = strtolower(substr((string) $locale, 0, 2));

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => LocalizedText::resolveOn($category, 'name', $locale) ?? '',
            'name_i18n' => LocalizedText::primaryOnly($category->name),
            'description' => LocalizedText::resolveOn($category, 'description', $locale),
            'image_path' => $category->image_path,
            'sort_order' => $category->sort_order,
            'products' => $category->products->map(fn (Product $product) => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => LocalizedText::resolveOn($product, 'name', $locale) ?? '',
                'name_i18n' => LocalizedText::primaryOnly($product->name),
                'description' => LocalizedText::resolveOn($product, 'description', $locale),
                'price' => (float) $product->price,
                'image_path' => $product->image_path,
                'image_url' => $product->imageUrl(),
                'allergens' => $product->allergens ?? [],
                'tags' => $product->tags->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'slug' => $tag->slug,
                    'name' => LocalizedText::resolveOn($tag, 'name', $locale) ?? '',
                ])->values(),
                'variant_groups' => $product->variantGroups->map(fn (ProductVariantGroup $group) => [
                    'id' => $group->id,
                    'name' => LocalizedText::resolveOn($group, 'name', $locale) ?? '',
                    'name_i18n' => LocalizedText::primaryOnly($group->name),
                    'is_required' => (bool) $group->is_required,
                    'options' => $group->options->map(fn (ProductVariantOption $option) => [
                        'id' => $option->id,
                        'name' => LocalizedText::resolveOn($option, 'name', $locale) ?? '',
                        'name_i18n' => LocalizedText::primaryOnly($option->name),
                        'price' => (float) $option->price,
                    ])->values(),
                ])->values(),
                'addon_groups' => $product->addonGroups->map(fn (ProductAddonGroup $group) => [
                    'id' => $group->id,
                    'name' => LocalizedText::resolveOn($group, 'name', $locale) ?? '',
                    'name_i18n' => LocalizedText::primaryOnly($group->name),
                    'min_selections' => (int) $group->min_selections,
                    'max_selections' => $group->max_selections !== null ? (int) $group->max_selections : null,
                    'addons' => $group->addons->map(fn (ProductAddon $addon) => [
                        'id' => $addon->id,
                        'name' => LocalizedText::resolveOn($addon, 'name', $locale) ?? '',
                        'name_i18n' => LocalizedText::primaryOnly($addon->name),
                        'price' => (float) $addon->price,
                        'min_quantity' => (int) $addon->min_quantity,
                        'max_quantity' => (int) $addon->max_quantity,
                    ])->values(),
                ])->values(),
                'is_available' => $product->is_available,
                'station' => $product->station ?? 'kitchen',
                'prep_time_minutes' => $product->prep_time_minutes,
                'track_inventory' => $product->track_inventory,
                'stock_quantity' => $product->track_inventory ? $product->stock_quantity : null,
            ]),
        ];
    }
}
