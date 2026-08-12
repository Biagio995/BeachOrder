<?php

namespace App\Contracts\Pos;

readonly class PosProductMapping
{
    public function __construct(
        public string $entityType,
        public int $localId,
        public string $externalId,
        public ?string $externalSku = null,
        public array $metadata = [],
    ) {}
}
