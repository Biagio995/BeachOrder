<?php

namespace App\Contracts\Pos;

readonly class PosOrderStatusResult
{
    public function __construct(
        public bool $success,
        public ?string $status = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}
}
