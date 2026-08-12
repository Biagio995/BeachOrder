<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductVariantOption;
use App\Support\LocalizedText;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductCustomizationService
{
    /**
     * Resolve and validate customer selections for a product line.
     *
     * @param  list<int>  $variantOptionIds
     * @param  list<array{id:int, quantity:int}>  $addonSelections
     * @return array{variants: list<array>, addons: list<array>, unit_extra: float}
     */
    public function resolveLine(Product $product, array $variantOptionIds, array $addonSelections, string $locale): array
    {
        $product->loadMissing([
            'variantGroups.options',
            'addonGroups.addons',
        ]);

        $variantSnapshots = $this->resolveVariants($product, $variantOptionIds, $locale);
        $addonSnapshots = $this->resolveAddons($product, $addonSelections, $locale);

        $unitExtra = 0.0;
        foreach ($variantSnapshots as $variant) {
            $unitExtra += (float) $variant['price'];
        }
        foreach ($addonSnapshots as $addon) {
            $unitExtra += (float) $addon['price'] * (int) $addon['quantity'];
        }

        return [
            'variants' => $variantSnapshots,
            'addons' => $addonSnapshots,
            'unit_extra' => $unitExtra,
        ];
    }

    /**
     * @param  list<int>  $variantOptionIds
     * @return list<array{group_id:int, group_name:string, option_id:int, option_name:string, price:float}>
     */
    private function resolveVariants(Product $product, array $variantOptionIds, string $locale): array
    {
        $optionIds = array_values(array_unique(array_map('intval', $variantOptionIds)));
        $groups = $product->variantGroups->where('is_active', true)->values();

        /** @var Collection<int, ProductVariantOption> $optionsById */
        $optionsById = $groups
            ->flatMap(fn ($group) => $group->options->where('is_active', true))
            ->keyBy('id');

        foreach ($optionIds as $optionId) {
            if (! $optionsById->has($optionId)) {
                throw ValidationException::withMessages([
                    'items' => ['One or more variants are invalid for this product.'],
                ]);
            }
        }

        $selectedByGroup = [];
        foreach ($optionIds as $optionId) {
            $option = $optionsById->get($optionId);
            $groupId = (int) $option->product_variant_group_id;
            if (isset($selectedByGroup[$groupId])) {
                throw ValidationException::withMessages([
                    'items' => ['Only one option can be selected per variant group.'],
                ]);
            }
            $selectedByGroup[$groupId] = $option;
        }

        $snapshots = [];
        foreach ($groups as $group) {
            $selected = $selectedByGroup[$group->id] ?? null;
            if ($group->is_required && ! $selected) {
                $groupName = LocalizedText::resolveOn($group, 'name', $locale) ?? $group->translatedName($locale);
                throw ValidationException::withMessages([
                    'items' => ["Variant \"{$groupName}\" is required."],
                ]);
            }

            if (! $selected) {
                continue;
            }

            $snapshots[] = [
                'group_id' => (int) $group->id,
                'group_name' => LocalizedText::resolveOn($group, 'name', $locale) ?? $group->translatedName($locale),
                'option_id' => (int) $selected->id,
                'option_name' => LocalizedText::resolveOn($selected, 'name', $locale) ?? $selected->translatedName($locale),
                'price' => (float) $selected->price,
            ];
        }

        return $snapshots;
    }

    /**
     * @param  list<array{id:int, quantity:int}>  $addonSelections
     * @return list<array{group_id:int, group_name:string, id:int, name:string, price:float, quantity:int}>
     */
    private function resolveAddons(Product $product, array $addonSelections, string $locale): array
    {
        $normalized = [];
        foreach ($addonSelections as $selection) {
            $id = (int) ($selection['id'] ?? 0);
            $qty = (int) ($selection['quantity'] ?? 0);
            if ($id <= 0 || $qty <= 0) {
                continue;
            }
            $normalized[$id] = ($normalized[$id] ?? 0) + $qty;
        }

        $groups = $product->addonGroups->where('is_active', true)->values();
        /** @var Collection<int, ProductAddon> $addonsById */
        $addonsById = $groups
            ->flatMap(fn ($group) => $group->addons->where('is_active', true))
            ->keyBy('id');

        foreach ($normalized as $addonId => $qty) {
            if (! $addonsById->has($addonId)) {
                throw ValidationException::withMessages([
                    'items' => ['One or more add-ons are invalid for this product.'],
                ]);
            }

            $addon = $addonsById->get($addonId);
            $min = max(0, (int) $addon->min_quantity);
            $max = max($min, (int) $addon->max_quantity);
            if ($qty < $min || $qty > $max) {
                $name = LocalizedText::resolveOn($addon, 'name', $locale) ?? $addon->translatedName($locale);
                throw ValidationException::withMessages([
                    'items' => ["Add-on \"{$name}\" quantity must be between {$min} and {$max}."],
                ]);
            }
        }

        $selectedCountByGroup = [];
        foreach ($normalized as $addonId => $qty) {
            $addon = $addonsById->get($addonId);
            $groupId = (int) $addon->product_addon_group_id;
            $selectedCountByGroup[$groupId] = ($selectedCountByGroup[$groupId] ?? 0) + 1;
        }

        foreach ($groups as $group) {
            $selectedCount = $selectedCountByGroup[$group->id] ?? 0;
            $minSelections = max(0, (int) $group->min_selections);
            $maxSelections = $group->max_selections !== null ? (int) $group->max_selections : null;
            $groupName = LocalizedText::resolveOn($group, 'name', $locale) ?? $group->translatedName($locale);

            if ($selectedCount < $minSelections) {
                throw ValidationException::withMessages([
                    'items' => ["Select at least {$minSelections} add-on(s) in \"{$groupName}\"."],
                ]);
            }
            if ($maxSelections !== null && $selectedCount > $maxSelections) {
                throw ValidationException::withMessages([
                    'items' => ["Select at most {$maxSelections} add-on(s) in \"{$groupName}\"."],
                ]);
            }
        }

        $snapshots = [];
        foreach ($normalized as $addonId => $qty) {
            $addon = $addonsById->get($addonId);
            $group = $groups->firstWhere('id', $addon->product_addon_group_id);
            $snapshots[] = [
                'group_id' => (int) $addon->product_addon_group_id,
                'group_name' => $group
                    ? (LocalizedText::resolveOn($group, 'name', $locale) ?? $group->translatedName($locale))
                    : '',
                'id' => (int) $addon->id,
                'name' => LocalizedText::resolveOn($addon, 'name', $locale) ?? $addon->translatedName($locale),
                'price' => (float) $addon->price,
                'quantity' => $qty,
            ];
        }

        usort($snapshots, fn ($a, $b) => [$a['group_id'], $a['id']] <=> [$b['group_id'], $b['id']]);

        return $snapshots;
    }
}
