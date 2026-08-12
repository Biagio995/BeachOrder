<?php

namespace App\Services\Printing;

use RuntimeException;

class EscposTcpDriver implements PrintDriverInterface
{
    public function send(string $payload, array $stationConfig, array $printingConfig): void
    {
        $host = trim((string) ($stationConfig['host'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('Printer host not configured.');
        }

        $port = (int) ($stationConfig['port'] ?? 9100);
        $timeout = 5;

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            throw new RuntimeException("Cannot connect to printer at {$host}:{$port} — {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        $written = fwrite($socket, $payload);
        if ($written === false || $written < strlen($payload)) {
            fclose($socket);
            throw new RuntimeException('Failed to send data to printer.');
        }

        fclose($socket);
    }
}
