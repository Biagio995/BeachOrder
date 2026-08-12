<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosOrderSync;
use App\Services\Pos\PosOrderSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosOrderSyncController extends Controller
{
    public function __construct(
        private readonly PosOrderSyncService $syncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PosOrderSync::query()
            ->with(['order:id,order_number,location_id,status,total', 'order.location:id,name,code'])
            ->latest();

        if ($status = $request->query('sync_status')) {
            $query->whereIn('sync_status', array_map('trim', explode(',', (string) $status)));
        }

        if ($orderId = $request->query('order_id')) {
            $query->where('order_id', $orderId);
        }

        return response()->json($query->paginate(50));
    }

    public function show(PosOrderSync $posOrderSync): JsonResponse
    {
        $posOrderSync->load([
            'order.items',
            'order.location',
            'attempts' => fn ($q) => $q->orderByDesc('attempt_number'),
        ]);

        return response()->json($posOrderSync);
    }

    public function retry(PosOrderSync $posOrderSync): JsonResponse
    {
        $sync = $this->syncService->retryManually($posOrderSync);

        return response()->json($sync->fresh(['order', 'attempts']));
    }
}
