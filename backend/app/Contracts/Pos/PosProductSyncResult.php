<?php

namespace App\Contracts\Pos;

readonly class PosProductSyncResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public array $metadata = [],
    ) {}
}
