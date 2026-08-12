<?php

namespace App\Contracts\Pos;

readonly class PosExternalProduct
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $sku = null,
        public array $metadata = [],
    ) {}
}
