<?php

namespace App\Contracts\Pos;

readonly class PosOrderPayload
{
    /**
     * @param  list<PosOrderLine>  $lines
     */
    public function __construct(
        public string $idempotencyKey,
        public string $orderNumber,
        public ?string $tableCode,
        public ?string $tableName,
        public ?string $customerName,
        public ?string $notes,
        public float $subtotal,
        public float $total,
        public string $currency,
        public array $lines,
        public array $metadata = [],
    ) {}
}
