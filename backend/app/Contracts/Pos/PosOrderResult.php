<?php

namespace App\Contracts\Pos;

readonly class PosOrderResult
{
    public function __construct(
        public bool $success,
        public ?string $externalOrderId = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}
}
