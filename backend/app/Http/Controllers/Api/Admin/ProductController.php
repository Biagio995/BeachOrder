<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\ProductVariantGroup;
use App\Models\ProductVariantOption;
use App\Models\Tag;
use App\Services\AuditLogger;
use App\Support\LocalizedName;
use App\Support\LocalizedText;
use App\Support\TenantContext;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with([
                'category',
                'tags',
                'variantGroups.options',
                'addonGroups.addons',
            ])
            ->orderBy('sort_order');

        if ($request->filled('station')) {
            $query->where('station', $request->string('station')->toString());
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->has('is_available')) {
            $query->where('is_available', $request->boolean('is_available'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $like = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('lower(cast(name as text)) like ?', [$like])
                    ->orWhereRaw('lower(slug) like ?', [$like]);
            });
        }

        if ($request->boolean('low_stock')) {
            $query->where('track_inventory', true)
                ->whereNotNull('low_stock_threshold')
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        $locale = strtolower(substr((string) $request->header('X-Locale', $request->query('locale', 'el')), 0, 2));
        $products = $query->get();
        foreach ($products as $product) {
            LocalizedText::applyResolvedForResponse($product, 'name', $locale);
            LocalizedText::applyResolvedForResponse($product, 'description', $locale);
            foreach ($product->tags as $tag) {
                LocalizedText::applyResolvedForResponse($tag, 'name', $locale);
            }
            foreach ($product->variantGroups as $group) {
                LocalizedText::applyResolvedForResponse($group, 'name', $locale);
                foreach ($group->options as $option) {
                    LocalizedText::applyResolvedForResponse($option, 'name', $locale);
                }
            }
            foreach ($product->addonGroups as $group) {
                LocalizedText::applyResolvedForResponse($group, 'name', $locale);
                foreach ($group->addons as $addon) {
                    LocalizedText::applyResolvedForResponse($addon, 'name', $locale);
                }
            }
            if ($product->category) {
                LocalizedText::applyResolvedForResponse($product->category, 'name', $locale);
            }
        }

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        [$data, $tagIds, $variantGroups, $addonGroups] = $this->validatedPayload($request);
        $data['slug'] = $data['slug'] ?? Str::slug(LocalizedName::slugSource($data['name']) ?: Str::random(8));

        $product = DB::transaction(function () use ($data, $tagIds, $variantGroups, $addonGroups) {
            $product = Product::create($data);
            $product->tags()->sync($tagIds);
            $this->syncVariantGroups($product, $variantGroups);
            $this->syncAddonGroups($product, $addonGroups);
            LocalizedText::fillMissingLocales($product, 'name');
            LocalizedText::fillMissingLocales($product, 'description');

            return $product;
        });

        AuditLogger::log('product.created', $product, null, $product->toArray());

        return response()->json($product->fresh()->load([
            'category',
            'tags',
            'variantGroups.options',
            'addonGroups.addons',
        ]), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load([
            'category',
            'tags',
            'variantGroups.options',
            'addonGroups.addons',
        ]));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $old = $product->toArray();
        [$data, $tagIds, $variantGroups, $addonGroups] = $this->validatedPayload($request, false);

        DB::transaction(function () use ($request, $product, $data, $tagIds, $variantGroups, $addonGroups) {
            $product->update($data);

            if ($request->exists('tag_ids')) {
                $product->tags()->sync($tagIds);
            }

            if ($request->exists('variant_groups')) {
                $this->syncVariantGroups($product, $variantGroups);
            }

            if ($request->exists('addon_groups')) {
                $this->syncAddonGroups($product, $addonGroups);
            }

            LocalizedText::fillMissingLocales($product, 'name');
            LocalizedText::fillMissingLocales($product, 'description');
        });

        AuditLogger::log('product.updated', $product, $old, $product->fresh()->toArray());

        return response()->json($product->fresh()->load([
            'category',
            'tags',
            'variantGroups.options',
            'addonGroups.addons',
        ]));
    }

    public function destroy(Product $product): JsonResponse
    {
        AuditLogger::log('product.deleted', $product, $product->toArray());
        $product->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $disk = config('filesystems.uploads_disk', 'public');
        $path = $request->file('image')->store(
            'tenants/'.TenantContext::id().'/products',
            $disk
        );

        $oldPath = $product->image_path;
        $old = ['image_path' => $oldPath];
        $product->update(['image_path' => $path]);
        AuditLogger::log('product.image_uploaded', $product, $old, ['image_path' => $path]);

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
            Storage::disk($disk)->delete($oldPath);
        }

        return response()->json($this->productImagePayload($product));
    }

    public function deleteImage(Product $product): JsonResponse
    {
        $oldPath = $product->image_path;
        if (! is_string($oldPath) || $oldPath === '') {
            return response()->json($this->productImagePayload($product));
        }

        $product->update(['image_path' => null]);
        AuditLogger::log('product.image_removed', $product, ['image_path' => $oldPath], ['image_path' => null]);

        Storage::disk(config('filesystems.uploads_disk', 'public'))->delete($oldPath);

        return response()->json($this->productImagePayload($product));
    }

    /** @return array<string, mixed> */
    private function productImagePayload(Product $product): array
    {
        $payload = $product->load([
            'category',
            'tags',
            'variantGroups.options',
            'addonGroups.addons',
        ])->toArray();
        $payload['image_url'] = $product->imageUrl();

        return $payload;
    }

    /**
     * @return array{0: array, 1: list<int>, 2: list<array>|null, 3: list<array>|null}
     */
    private function validatedPayload(Request $request, bool $creating = true): array
    {
        $data = $request->validate([
            'category_id' => [$creating ? 'required' : 'sometimes', TenantRules::exists('categories')],
            'station' => ['nullable', 'string', 'in:kitchen,bar'],
            'name' => [$creating ? 'required' : 'sometimes', 'array'],
            'name.el' => ['nullable', 'string', 'max:150'],
            'name.en' => ['nullable', 'string', 'max:150'],
            'name.it' => ['nullable', 'string', 'max:150'],
            'name.de' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'array'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                TenantRules::unique('products', 'slug')
                    ->ignore($request->route('product')?->id),
            ],
            'price' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'image_path' => ['nullable', 'string'],
            'allergens' => ['nullable', 'array'],
            'is_available' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'track_inventory' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'tag_ids' => [$creating ? 'nullable' : 'sometimes', 'array', 'max:'.Tag::MAX_PER_PRODUCT],
            'tag_ids.*' => [TenantRules::exists('tags')],

            'variant_groups' => [$creating ? 'nullable' : 'sometimes', 'array', 'max:20'],
            'variant_groups.*.id' => ['nullable', 'integer'],
            'variant_groups.*.name' => ['required_with:variant_groups', 'array'],
            'variant_groups.*.name.el' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.name.en' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.name.it' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.name.de' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.is_required' => ['nullable', 'boolean'],
            'variant_groups.*.is_active' => ['nullable', 'boolean'],
            'variant_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variant_groups.*.options' => ['nullable', 'array', 'max:30'],
            'variant_groups.*.options.*.id' => ['nullable', 'integer'],
            'variant_groups.*.options.*.name' => ['required_with:variant_groups.*.options', 'array'],
            'variant_groups.*.options.*.name.el' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.options.*.name.en' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.options.*.name.it' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.options.*.name.de' => ['nullable', 'string', 'max:150'],
            'variant_groups.*.options.*.price' => ['required_with:variant_groups.*.options', 'numeric', 'min:0'],
            'variant_groups.*.options.*.is_active' => ['nullable', 'boolean'],
            'variant_groups.*.options.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'addon_groups' => [$creating ? 'nullable' : 'sometimes', 'array', 'max:20'],
            'addon_groups.*.id' => ['nullable', 'integer'],
            'addon_groups.*.name' => ['required_with:addon_groups', 'array'],
            'addon_groups.*.name.el' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.name.en' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.name.it' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.name.de' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.min_selections' => ['nullable', 'integer', 'min:0'],
            'addon_groups.*.max_selections' => ['nullable', 'integer', 'min:0'],
            'addon_groups.*.is_active' => ['nullable', 'boolean'],
            'addon_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'addon_groups.*.addons' => ['nullable', 'array', 'max:40'],
            'addon_groups.*.addons.*.id' => ['nullable', 'integer'],
            'addon_groups.*.addons.*.name' => ['required_with:addon_groups.*.addons', 'array'],
            'addon_groups.*.addons.*.name.el' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.addons.*.name.en' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.addons.*.name.it' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.addons.*.name.de' => ['nullable', 'string', 'max:150'],
            'addon_groups.*.addons.*.price' => ['required_with:addon_groups.*.addons', 'numeric', 'min:0'],
            'addon_groups.*.addons.*.is_active' => ['nullable', 'boolean'],
            'addon_groups.*.addons.*.min_quantity' => ['nullable', 'integer', 'min:0'],
            'addon_groups.*.addons.*.max_quantity' => ['nullable', 'integer', 'min:0'],
            'addon_groups.*.addons.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($data['name'])) {
            LocalizedName::assertPrimaryLocale($data['name'], $creating || array_key_exists('name', $request->all()));
        }

        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'] ?? [])));
        unset($data['tag_ids']);

        $variantGroups = null;
        if (array_key_exists('variant_groups', $data)) {
            $variantGroups = [];
            foreach ($data['variant_groups'] ?? [] as $gIndex => $group) {
                LocalizedName::assertPrimaryLocale($group['name'] ?? [], true);
                $options = [];
                foreach ($group['options'] ?? [] as $oIndex => $option) {
                    LocalizedName::assertPrimaryLocale($option['name'] ?? [], true);
                    $options[] = [
                        'id' => isset($option['id']) ? (int) $option['id'] : null,
                        'name' => $option['name'],
                        'price' => $option['price'],
                        'is_active' => array_key_exists('is_active', $option) ? (bool) $option['is_active'] : true,
                        'sort_order' => $option['sort_order'] ?? $oIndex,
                    ];
                }
                $variantGroups[] = [
                    'id' => isset($group['id']) ? (int) $group['id'] : null,
                    'name' => $group['name'],
                    'is_required' => array_key_exists('is_required', $group) ? (bool) $group['is_required'] : true,
                    'is_active' => array_key_exists('is_active', $group) ? (bool) $group['is_active'] : true,
                    'sort_order' => $group['sort_order'] ?? $gIndex,
                    'options' => $options,
                ];
            }
            unset($data['variant_groups']);
        }

        $addonGroups = null;
        if (array_key_exists('addon_groups', $data)) {
            $addonGroups = [];
            foreach ($data['addon_groups'] ?? [] as $gIndex => $group) {
                LocalizedName::assertPrimaryLocale($group['name'] ?? [], true);
                $minSelections = (int) ($group['min_selections'] ?? 0);
                $maxSelections = array_key_exists('max_selections', $group) && $group['max_selections'] !== null
                    ? (int) $group['max_selections']
                    : null;
                if ($maxSelections !== null && $maxSelections < $minSelections) {
                    $maxSelections = $minSelections;
                }

                $addons = [];
                foreach ($group['addons'] ?? [] as $aIndex => $addon) {
                    LocalizedName::assertPrimaryLocale($addon['name'] ?? [], true);
                    $minQty = (int) ($addon['min_quantity'] ?? 0);
                    $maxQty = (int) ($addon['max_quantity'] ?? 1);
                    if ($maxQty < max(1, $minQty)) {
                        $maxQty = max(1, $minQty);
                    }
                    $addons[] = [
                        'id' => isset($addon['id']) ? (int) $addon['id'] : null,
                        'name' => $addon['name'],
                        'price' => $addon['price'],
                        'is_active' => array_key_exists('is_active', $addon) ? (bool) $addon['is_active'] : true,
                        'min_quantity' => $minQty,
                        'max_quantity' => $maxQty,
                        'sort_order' => $addon['sort_order'] ?? $aIndex,
                    ];
                }

                $addonGroups[] = [
                    'id' => isset($group['id']) ? (int) $group['id'] : null,
                    'name' => $group['name'],
                    'min_selections' => $minSelections,
                    'max_selections' => $maxSelections,
                    'is_active' => array_key_exists('is_active', $group) ? (bool) $group['is_active'] : true,
                    'sort_order' => $group['sort_order'] ?? $gIndex,
                    'addons' => $addons,
                ];
            }
            unset($data['addon_groups']);
        }

        // Ignore legacy flat addons key if still sent.
        unset($data['addons']);

        return [$data, $tagIds, $variantGroups, $addonGroups];
    }

    /**
     * @param  list<array>|null  $groups
     */
    private function syncVariantGroups(Product $product, ?array $groups): void
    {
        if ($groups === null) {
            return;
        }

        $keptGroupIds = [];

        foreach ($groups as $gIndex => $groupData) {
            $payload = [
                'name' => $groupData['name'],
                'is_required' => $groupData['is_required'],
                'is_active' => $groupData['is_active'],
                'sort_order' => $groupData['sort_order'] ?? $gIndex,
            ];

            $group = null;
            if ($groupData['id']) {
                $group = ProductVariantGroup::query()
                    ->where('product_id', $product->id)
                    ->where('id', $groupData['id'])
                    ->first();
            }

            if ($group) {
                $group->update($payload);
            } else {
                $group = $product->variantGroups()->create($payload);
            }

            LocalizedText::fillMissingLocales($group, 'name');
            $keptGroupIds[] = $group->id;
            $keptOptionIds = [];

            foreach ($groupData['options'] as $oIndex => $optionData) {
                $optionPayload = [
                    'name' => $optionData['name'],
                    'price' => $optionData['price'],
                    'is_active' => $optionData['is_active'],
                    'sort_order' => $optionData['sort_order'] ?? $oIndex,
                ];

                $option = null;
                if ($optionData['id']) {
                    $option = ProductVariantOption::query()
                        ->where('product_variant_group_id', $group->id)
                        ->where('id', $optionData['id'])
                        ->first();
                }

                if ($option) {
                    $option->update($optionPayload);
                } else {
                    $option = $group->options()->create($optionPayload);
                }

                LocalizedText::fillMissingLocales($option, 'name');
                $keptOptionIds[] = $option->id;
            }

            $group->options()
                ->when($keptOptionIds !== [], fn ($q) => $q->whereNotIn('id', $keptOptionIds))
                ->delete();
        }

        $product->variantGroups()
            ->when($keptGroupIds !== [], fn ($q) => $q->whereNotIn('id', $keptGroupIds))
            ->delete();
    }

    /**
     * @param  list<array>|null  $groups
     */
    private function syncAddonGroups(Product $product, ?array $groups): void
    {
        if ($groups === null) {
            return;
        }

        $keptGroupIds = [];

        foreach ($groups as $gIndex => $groupData) {
            $payload = [
                'name' => $groupData['name'],
                'min_selections' => $groupData['min_selections'],
                'max_selections' => $groupData['max_selections'],
                'is_active' => $groupData['is_active'],
                'sort_order' => $groupData['sort_order'] ?? $gIndex,
            ];

            $group = null;
            if ($groupData['id']) {
                $group = ProductAddonGroup::query()
                    ->where('product_id', $product->id)
                    ->where('id', $groupData['id'])
                    ->first();
            }

            if ($group) {
                $group->update($payload);
            } else {
                $group = $product->addonGroups()->create($payload);
            }

            LocalizedText::fillMissingLocales($group, 'name');
            $keptGroupIds[] = $group->id;
            $keptAddonIds = [];

            foreach ($groupData['addons'] as $aIndex => $addonData) {
                $addonPayload = [
                    'product_id' => $product->id,
                    'name' => $addonData['name'],
                    'price' => $addonData['price'],
                    'is_active' => $addonData['is_active'],
                    'min_quantity' => $addonData['min_quantity'],
                    'max_quantity' => $addonData['max_quantity'],
                    'sort_order' => $addonData['sort_order'] ?? $aIndex,
                ];

                $addon = null;
                if ($addonData['id']) {
                    $addon = ProductAddon::query()
                        ->where('product_addon_group_id', $group->id)
                        ->where('id', $addonData['id'])
                        ->first();
                }

                if ($addon) {
                    $addon->update($addonPayload);
                } else {
                    $addon = $group->addons()->create($addonPayload);
                }

                LocalizedText::fillMissingLocales($addon, 'name');
                $keptAddonIds[] = $addon->id;
            }

            $group->addons()
                ->when($keptAddonIds !== [], fn ($q) => $q->whereNotIn('id', $keptAddonIds))
                ->delete();
        }

        $product->addonGroups()
            ->when($keptGroupIds !== [], fn ($q) => $q->whereNotIn('id', $keptGroupIds))
            ->delete();
    }
}
