<?php

namespace App\Services\Printing;

interface PrintDriverInterface
{
    /**
     * Send raw ticket payload to the printer.
     *
     * @param  array<string, mixed>  $stationConfig
     */
    public function send(string $payload, array $stationConfig, array $printingConfig): void;
}
