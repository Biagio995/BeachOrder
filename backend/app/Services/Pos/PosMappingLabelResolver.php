<?php

namespace App\Services\Pos;

use App\Models\Category;
use App\Models\PosMapping;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductVariantOption;
use App\Support\LocalizedText;

class PosMappingLabelResolver
{
    /**
     * Resolve a human-readable label for a mapped entity (same LocalizedText rules as orders).
     */
    public function resolve(string $entityType, int $localId, string $locale): string
    {
        if ($localId <= 0) {
            return '#0';
        }

        $label = match ($entityType) {
            'product' => $this->productLabel($localId, $locale),
            'variant_option' => $this->variantOptionLabel($localId, $locale),
            'addon' => $this->addonLabel($localId, $locale),
            'category' => $this->categoryLabel($localId, $locale),
            default => null,
        };

        if ($label !== null && $label !== '') {
            return "{$label} (#{$localId})";
        }

        return "#{$localId}";
    }

    /**
     * @return list<array{id: int, label: string, name: array<string, string>}>
     */
    public function listEntities(string $entityType, string $locale): array
    {
        return match ($entityType) {
            'product' => Product::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Product $product) => $this->entityOption('product', $product->id, $locale))
                ->all(),
            'variant_option' => ProductVariantOption::query()
                ->with('group')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ProductVariantOption $option) => $this->entityOption('variant_option', $option->id, $locale))
                ->all(),
            'addon' => ProductAddon::query()
                ->with('group')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ProductAddon $addon) => $this->entityOption('addon', $addon->id, $locale))
                ->all(),
            'category' => Category::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category) => $this->entityOption('category', $category->id, $locale))
                ->all(),
            default => [],
        };
    }

    /**
     * @return array{id: int, label: string, name: array<string, string>}
     */
    public function entityOption(string $entityType, int $localId, string $locale): array
    {
        return [
            'id' => $localId,
            'label' => $this->resolve($entityType, $localId, $locale),
            'name' => $this->nameMap($entityType, $localId),
        ];
    }

    /**
     * Raw localized names (no #id suffix) for client-side locale switching.
     *
     * @return array<string, string>
     */
    public function nameMap(string $entityType, int $localId): array
    {
        $locales = config('translation.supported_locales', ['it', 'en', 'el', 'de']);
        $map = [];

        foreach ($locales as $code) {
            $label = match ($entityType) {
                'product' => $this->productLabel($localId, $code),
                'variant_option' => $this->variantOptionLabel($localId, $code),
                'addon' => $this->addonLabel($localId, $code),
                'category' => $this->categoryLabel($localId, $code),
                default => null,
            };

            if ($label !== null && $label !== '') {
                $map[$code] = $label;
            }
        }

        return $map;
    }

    public function requestLocale(?string $headerLocale = null, ?string $queryLocale = null): string
    {
        return strtolower(substr((string) ($headerLocale ?: $queryLocale ?: 'en'), 0, 2));
    }

    private function productLabel(int $id, string $locale): ?string
    {
        $product = Product::query()->find($id);
        if (! $product) {
            return null;
        }

        return LocalizedText::resolveOn($product, 'name', $locale)
            ?? $product->translatedName($locale);
    }

    private function variantOptionLabel(int $id, string $locale): ?string
    {
        $option = ProductVariantOption::query()->with('group')->find($id);
        if (! $option) {
            return null;
        }

        $groupName = $option->group
            ? (LocalizedText::resolveOn($option->group, 'name', $locale) ?? $option->group->translatedName($locale))
            : null;
        $optionName = LocalizedText::resolveOn($option, 'name', $locale) ?? $option->translatedName($locale);

        if ($groupName && $optionName) {
            return "{$groupName}: {$optionName}";
        }

        return $optionName ?: $groupName;
    }

    private function addonLabel(int $id, string $locale): ?string
    {
        $addon = ProductAddon::query()->with('group')->find($id);
        if (! $addon) {
            return null;
        }

        $addonName = LocalizedText::resolveOn($addon, 'name', $locale) ?? $addon->translatedName($locale);
        $groupName = $addon->group
            ? (LocalizedText::resolveOn($addon->group, 'name', $locale) ?? $addon->group->translatedName($locale))
            : null;

        if ($addonName && $groupName) {
            return "{$addonName} ({$groupName})";
        }

        return $addonName ?: $groupName;
    }

    private function categoryLabel(int $id, string $locale): ?string
    {
        $category = Category::query()->find($id);
        if (! $category) {
            return null;
        }

        return LocalizedText::resolveOn($category, 'name', $locale)
            ?? $category->translatedName($locale);
    }
}
