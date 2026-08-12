<?php

namespace App\Services\Printing;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrintOrderStationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $orderId,
        public string $station,
        public bool $reprint = false,
    ) {}

    public function handle(PrintService $printService): void
    {
        $order = Order::query()->with(['items', 'location', 'tenant'])->find($this->orderId);
        if (! $order) {
            return;
        }

        if (! $printService->shouldPrintOrder($order)) {
            return;
        }

        try {
            $printService->printStation($order, $this->station, $this->reprint);
        } catch (\Throwable $e) {
            Log::warning('Kitchen print failed', [
                'order_id' => $this->orderId,
                'station' => $this->station,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
