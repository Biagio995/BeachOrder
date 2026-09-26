<?php

namespace App\Services\Printing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrintNodeDriver implements PrintDriverInterface
{
    public function send(string $payload, array $stationConfig, array $printingConfig): void
    {
        $apiKey = $this->resolveApiKey($printingConfig);
        $printerId = trim((string) ($stationConfig['host'] ?? ''));
        if ($printerId === '') {
            throw new RuntimeException('PrintNode printer ID not configured (use host field).');
        }

        $response = Http::withBasicAuth($apiKey, '')
            ->timeout(15)
            ->post('https://api.printnode.com/printjobs', [
                'printerId' => (int) $printerId,
                'title' => sprintf('%s ticket', (string) config('app.name')),
                'contentType' => 'raw_base64',
                'content' => base64_encode($payload),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PrintNode error: '.$response->body());
        }
    }

    /**
     * @param  array<string, mixed>  $printingConfig
     */
    private function resolveApiKey(array $printingConfig): string
    {
        $ref = trim((string) ($printingConfig['credentials_ref'] ?? ''));
        if ($ref !== '' && str_starts_with($ref, 'env:')) {
            $key = env(substr($ref, 4));
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }

        $key = config('services.printnode.api_key');
        if (is_string($key) && $key !== '') {
            return $key;
        }

        throw new RuntimeException('PrintNode API key not configured.');
    }
}
