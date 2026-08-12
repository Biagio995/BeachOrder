<?php

namespace App\Contracts\Pos;

readonly class PosOrderLine
{
    public function __construct(
        public string $externalProductId,
        public int $quantity,
        public float $unitPrice,
        public ?string $notes = null,
        public array $modifiers = [],
        public array $metadata = [],
    ) {}
}
