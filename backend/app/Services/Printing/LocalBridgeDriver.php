<?php

namespace App\Services\Printing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LocalBridgeDriver implements PrintDriverInterface
{
    public function send(string $payload, array $stationConfig, array $printingConfig): void
    {
        $url = trim((string) ($stationConfig['host'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('Local bridge URL not configured.');
        }

        $response = Http::timeout(10)
            ->post(rtrim($url, '/').'/print', [
                'payload_base64' => base64_encode($payload),
                'station' => $stationConfig['_station'] ?? null,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Local bridge error: '.$response->body());
        }
    }
}
