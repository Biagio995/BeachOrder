<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function assertAvailable(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::query()->findOrFail($item['product_id']);

            if (! $product->is_available || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => ["Product {$product->translatedName()} is unavailable"],
                ]);
            }

            if ($product->track_inventory) {
                $stock = $product->stock_quantity ?? 0;
                if ($stock < (int) $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->translatedName()}"],
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function decrement(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $product = Product::query()->lockForUpdate()->find($item['product_id']);
                if (! $product || ! $product->track_inventory) {
                    continue;
                }

                $qty = (int) $item['quantity'];
                if (($product->stock_quantity ?? 0) < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->translatedName()}"],
                    ]);
                }

                $product->stock_quantity -= $qty;
                if ($product->stock_quantity <= 0) {
                    $product->stock_quantity = 0;
                    $product->is_available = false;
                }
                $product->save();
            }
        });
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function restore(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $product = Product::query()->lockForUpdate()->find($item['product_id']);
                if (! $product || ! $product->track_inventory) {
                    continue;
                }

                $product->stock_quantity = ($product->stock_quantity ?? 0) + (int) $item['quantity'];
                if ($product->stock_quantity > 0) {
                    $product->is_available = true;
                }
                $product->save();
            }
        });
    }

    public function restoreForOrder(Order $order): void
    {
        $order->loadMissing('items');

        $items = $order->items
            ->filter(fn ($item) => $item->product_id)
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
            ])
            ->values()
            ->all();

        if ($items !== []) {
            $this->restore($items);
        }
    }
}
