<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\OrderPrintLog;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use App\Support\DemoMode;
use App\Support\OrderNumber;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class PrintService
{
    public function __construct(
        private TicketFormatter $formatter,
        private EscposTcpDriver $escposTcp,
        private PrintNodeDriver $printNode,
        private LocalBridgeDriver $localBridge,
    ) {}

    public function shouldPrintOrder(Order $order): bool
    {
        // Demo kill-switch: printers stay off unless explicitly enabled, so
        // the public demo never attempts a TCP/printer connection.
        if (DemoMode::enabled() && ! DemoMode::printersEnabled()) {
            return false;
        }

        $tenant = $order->tenant ?? Tenant::query()->find($order->tenant_id);
        if (! $tenant || ! $tenant->printingEnabled()) {
            return false;
        }

        if ($order->status === 'cancelled') {
            return false;
        }

        if (in_array($order->payment_method, PaymentService::ONLINE_METHODS, true)
            && $order->payment_status !== 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Queue print jobs for all active stations on an order.
     */
    public function dispatchOrderPrint(Order $order, bool $reprint = false): void
    {
        if (! $this->shouldPrintOrder($order)) {
            return;
        }

        $order->loadMissing(['items', 'location', 'tenant']);

        foreach ($order->activeStations() as $station) {
            PrintOrderStationJob::dispatch($order->id, $station, $reprint);
        }
    }

    /**
     * Print ticket for a single station (called by job or reprint API).
     *
     * @return OrderPrintLog
     */
    public function printStation(Order $order, string $station, bool $reprint = false): OrderPrintLog
    {
        if (DemoMode::enabled() && ! DemoMode::printersEnabled()) {
            throw new RuntimeException('Printing is disabled in demo mode.');
        }

        if (! in_array($station, Order::STATIONS, true)) {
            throw new InvalidArgumentException("Invalid station: {$station}");
        }

        $order->loadMissing(['items', 'location', 'tenant']);
        $tenant = $order->tenant;

        if (! $tenant || ! $tenant->printingEnabled()) {
            throw new RuntimeException('Printing is not enabled for this venue.');
        }

        $config = $tenant->printingConfig();
        $stationConfig = (array) ($config['stations'][$station] ?? []);
        $copies = max(1, min(5, (int) ($stationConfig['copies'] ?? 1)));

        $log = OrderPrintLog::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'station' => $station,
            'driver' => (string) ($config['driver'] ?? 'escpos_tcp'),
            'status' => OrderPrintLog::STATUS_PENDING,
            'copies' => $copies,
            'is_reprint' => $reprint,
        ]);

        try {
            $this->assertStationConfigured($config['driver'] ?? 'escpos_tcp', $stationConfig);

            $items = $order->items->where('station', $station);
            if ($items->isEmpty()) {
                throw new RuntimeException("No items for station: {$station}");
            }

            $locale = $tenant->default_locale ?? 'it';
            $text = $this->formatter->formatText($order, $station, $items, $locale, $reprint);
            $payload = $this->formatter->formatEscPos($text);

            $driver = $this->resolveDriver((string) ($config['driver'] ?? 'escpos_tcp'));
            $stationConfig['_station'] = $station;

            for ($i = 0; $i < $copies; $i++) {
                $driver->send($payload, $stationConfig, $config);
            }

            $log->update([
                'status' => OrderPrintLog::STATUS_SUCCESS,
                'printed_at' => now(),
                'error_message' => null,
            ]);

            AuditLogger::log('order.printed', $order, null, [
                'station' => $station,
                'copies' => $copies,
                'reprint' => $reprint,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => OrderPrintLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            AuditLogger::log('order.print_failed', $order, null, [
                'station' => $station,
                'error' => $e->getMessage(),
                'reprint' => $reprint,
            ]);

            throw $e;
        }

        return $log->fresh();
    }

    /**
     * Build test ticket preview text without sending.
     */
    public function previewTest(Tenant $tenant, string $station): string
    {
        if (! in_array($station, Order::STATIONS, true)) {
            throw new InvalidArgumentException("Invalid station: {$station}");
        }

        $locale = $tenant->default_locale ?? 'it';

        return $this->buildTestTicketText($tenant, $station, $locale);
    }

    /**
     * Send a test ticket to verify printer connectivity.
     *
     * @return array{text: string, log: OrderPrintLog}
     */
    public function printTest(Tenant $tenant, string $station): array
    {
        if (DemoMode::enabled() && ! DemoMode::printersEnabled()) {
            throw new RuntimeException('Printing is disabled in demo mode.');
        }

        if (! in_array($station, Order::STATIONS, true)) {
            throw new InvalidArgumentException("Invalid station: {$station}");
        }

        if (! $tenant->printingEnabled()) {
            throw new RuntimeException('Printing is not enabled.');
        }

        $config = $tenant->printingConfig();
        $stationConfig = (array) ($config['stations'][$station] ?? []);
        $this->assertStationConfigured($config['driver'] ?? 'escpos_tcp', $stationConfig);

        $locale = $tenant->default_locale ?? 'it';
        $sampleText = $this->buildTestTicketText($tenant, $station, $locale);
        $payload = $this->formatter->formatEscPos($sampleText);

        $driver = $this->resolveDriver((string) ($config['driver'] ?? 'escpos_tcp'));
        $stationConfig['_station'] = $station;
        $driver->send($payload, $stationConfig, $config);

        $log = OrderPrintLog::create([
            'tenant_id' => $tenant->id,
            'order_id' => null,
            'station' => $station,
            'driver' => (string) ($config['driver'] ?? 'escpos_tcp'),
            'status' => OrderPrintLog::STATUS_SUCCESS,
            'copies' => 1,
            'is_reprint' => false,
            'printed_at' => now(),
        ]);

        return ['text' => $sampleText, 'log' => $log];
    }

    /**
     * @return Collection<int, OrderPrintLog>
     */
    public function latestLogsForOrder(Order $order): Collection
    {
        return OrderPrintLog::query()
            ->where('order_id', $order->id)
            ->latest()
            ->get();
    }

    private function resolveDriver(string $driver): PrintDriverInterface
    {
        return match ($driver) {
            'printnode' => $this->printNode,
            'local_bridge' => $this->localBridge,
            default => $this->escposTcp,
        };
    }

    /**
     * @param  array<string, mixed>  $stationConfig
     */
    private function assertStationConfigured(string $driver, array $stationConfig): void
    {
        $host = trim((string) ($stationConfig['host'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('Printer not configured for this station.');
        }

        if ($driver === 'escpos_tcp') {
            $port = (int) ($stationConfig['port'] ?? 0);
            if ($port < 1 || $port > 65535) {
                throw new RuntimeException('Invalid printer port.');
            }
        }
    }

    private function buildTestTicketText(Tenant $tenant, string $station, string $locale): string
    {
        $order = new Order([
            'order_number' => OrderNumber::prefix().'-TEST-00001',
            'notes' => $locale === 'it' ? 'Stampa di prova' : 'Test print',
            'created_at' => now(),
        ]);
        $order->setRelation('tenant', $tenant);

        $location = new \App\Models\Location([
            'name' => $locale === 'it' ? 'Tavolo 1' : 'Table 1',
            'type' => 'table',
            'zone' => 'A',
        ]);
        $order->setRelation('location', $location);

        $item = new \App\Models\OrderItem([
            'product_name' => $locale === 'it' ? 'Pizza Margherita' : 'Margherita Pizza',
            'quantity' => 1,
            'variants' => [['group_name' => 'Impasto', 'option_name' => 'Integrale']],
            'addons' => [['name' => 'Mozzarella extra', 'quantity' => 1]],
            'notes' => $locale === 'it' ? 'Ben cotta' : 'Well done',
        ]);

        return $this->formatter->formatText(
            $order,
            $station,
            collect([$item]),
            $locale,
            test: true,
        );
    }
}
