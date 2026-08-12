<?php

namespace App\Services\Pos;

use App\Contracts\Pos\PosOrderLine;
use App\Contracts\Pos\PosOrderPayload;
use App\Models\Order;
use App\Models\PosIntegration;
use App\Models\PosMapping;
use Illuminate\Validation\ValidationException;

class PosOrderPayloadBuilder
{
    public function build(Order $order, PosIntegration $integration, string $idempotencyKey): PosOrderPayload
    {
        $order->loadMissing(['items', 'location', 'tenant']);

        $lines = [];
        $missingMappings = [];

        foreach ($order->items as $item) {
            $externalProductId = $this->resolveExternalId(
                $integration,
                'product',
                (int) $item->product_id,
            );

            if ($externalProductId === null) {
                $missingMappings[] = "Product ID {$item->product_id} ({$item->product_name})";

                continue;
            }

            $modifiers = [];

            foreach ($item->variants ?? [] as $variant) {
                $optionId = (int) ($variant['option_id'] ?? 0);
                $externalVariantId = $this->resolveExternalId($integration, 'variant_option', $optionId);
                if ($externalVariantId) {
                    $modifiers[] = [
                        'type' => 'variant',
                        'external_id' => $externalVariantId,
                        'name' => $variant['option_name'] ?? null,
                        'price' => $variant['price'] ?? 0,
                    ];
                }
            }

            foreach ($item->addons ?? [] as $addon) {
                $addonId = (int) ($addon['id'] ?? 0);
                $externalAddonId = $this->resolveExternalId($integration, 'addon', $addonId);
                if ($externalAddonId) {
                    $modifiers[] = [
                        'type' => 'addon',
                        'external_id' => $externalAddonId,
                        'name' => $addon['name'] ?? null,
                        'quantity' => $addon['quantity'] ?? 1,
                        'price' => $addon['price'] ?? 0,
                    ];
                }
            }

            $lines[] = new PosOrderLine(
                externalProductId: $externalProductId,
                quantity: (int) $item->quantity,
                unitPrice: (float) $item->unit_price,
                notes: $item->notes,
                modifiers: $modifiers,
                metadata: [
                    'local_product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                ],
            );
        }

        if ($missingMappings !== []) {
            throw ValidationException::withMessages([
                'mapping' => ['Missing POS mappings: '.implode(', ', $missingMappings)],
            ]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'order' => ['Order has no mappable line items.'],
            ]);
        }

        return new PosOrderPayload(
            idempotencyKey: $idempotencyKey,
            orderNumber: $order->order_number,
            tableCode: $order->location?->code,
            tableName: $order->location?->name,
            customerName: $order->customer_name,
            notes: $order->notes,
            subtotal: (float) $order->subtotal,
            total: (float) $order->total,
            currency: strtoupper((string) ($order->tenant?->currency ?? 'EUR')),
            lines: $lines,
            metadata: [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
            ],
        );
    }

    private function resolveExternalId(PosIntegration $integration, string $entityType, int $localId): ?string
    {
        if ($localId <= 0) {
            return null;
        }

        $mapping = PosMapping::query()
            ->where('pos_integration_id', $integration->id)
            ->where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->first();

        return $mapping?->external_id;
    }
}
