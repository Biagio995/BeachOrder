<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Printing\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPrintController extends Controller
{
    public function __construct(private PrintService $printing) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'station' => ['nullable', 'string', 'in:'.implode(',', Order::STATIONS)],
        ]);

        $order->load(['items', 'location', 'tenant']);

        $stations = isset($data['station'])
            ? [$data['station']]
            : array_values(array_intersect($order->activeStations(), Order::STATIONS));

        if ($stations === []) {
            return response()->json(['message' => 'No printable stations for this order.'], 422);
        }

        $results = [];

        foreach ($stations as $station) {
            try {
                $log = $this->printing->printStation($order, $station, reprint: true);
                $results[] = [
                    'station' => $station,
                    'status' => $log->status,
                    'printed_at' => $log->printed_at?->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'station' => $station,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $allFailed = collect($results)->every(fn ($r) => ($r['status'] ?? '') === 'failed');

        return response()->json([
            'results' => $results,
        ], $allFailed ? 502 : 200);
    }

    public function logs(Order $order): JsonResponse
    {
        $logs = $this->printing->latestLogsForOrder($order);

        return response()->json(['data' => $logs]);
    }
}
