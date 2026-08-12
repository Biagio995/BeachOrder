<?php

namespace App\Http\Controllers\Api;

use App\Events\WaiterCallCreated;
use App\Events\WaiterCallUpdated;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\WaiterCall;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaiterCallController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_code' => ['required', 'string'],
            'reason' => ['required', 'string', 'in:'.implode(',', WaiterCall::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
            'customer_session' => ['required', 'uuid'],
        ]);

        $location = Location::query()
            ->where('code', $data['location_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $call = WaiterCall::create([
            'location_id' => $location->id,
            'reason' => $data['reason'],
            'note' => $data['note'] ?? null,
            'customer_session' => $data['customer_session'],
            'status' => 'pending',
        ]);

        $call->load('location');
        try {
            event(new WaiterCallCreated($call));
        } catch (\Throwable) {
            // Realtime is best-effort.
        }

        return response()->json($call, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = WaiterCall::query()->with('location')->latest();

        if ($status = $request->query('status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $status))));
            $query->whereIn('status', $statuses);
        } else {
            $query->whereIn('status', ['pending', 'acknowledged']);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($reason = $request->query('reason')) {
            $query->whereIn('reason', array_map('trim', explode(',', (string) $reason)));
        }

        return response()->json($query->paginate(50));
    }

    public function updateStatus(Request $request, WaiterCall $waiterCall): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:acknowledged,resolved'],
        ]);

        $waiterCall->status = $data['status'];
        $waiterCall->handled_by = $request->user()->id;

        if ($data['status'] === 'acknowledged') {
            $waiterCall->acknowledged_at = now();
        }

        if ($data['status'] === 'resolved') {
            $waiterCall->resolved_at = now();
        }

        $waiterCall->save();
        $waiterCall->load('location');

        AuditLogger::log('waiter_call.'.$data['status'], $waiterCall);

        try {
            event(new WaiterCallUpdated($waiterCall));
        } catch (\Throwable) {
            // Realtime is best-effort.
        }

        return response()->json($waiterCall);
    }
}
