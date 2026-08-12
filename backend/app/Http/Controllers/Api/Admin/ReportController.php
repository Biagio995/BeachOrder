<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\WaiterCall;
use App\Support\ReportDateRange;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $range = ReportDateRange::fromRequest($request);
        $locationId = $request->filled('location_id') ? $request->integer('location_id') : null;

        $metrics = $this->buildPeriodMetrics($range, $locationId);

        return response()->json([
            'filters' => [
                'period' => $range->period,
                'days' => $range->days,
                'location_id' => $locationId,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
            ],
            'total_orders' => $metrics['orders_active'],
            'total_revenue' => $metrics['revenue_period'],
            'average_order_value' => $metrics['avg_ticket'],
            'orders_by_day' => $metrics['orders_by_day'],
            'revenue_by_day' => $metrics['revenue_by_day'],
            'best_selling_products' => $metrics['top_products'],
            'orders_by_hour' => $metrics['orders_by_hour'],
            'orders_by_table' => $metrics['by_location'],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $range = ReportDateRange::fromRequest($request);
        $locationId = $request->filled('location_id') ? $request->integer('location_id') : null;

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', TenantContext::id())
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->when($locationId, fn ($q) => $q->where('orders.location_id', $locationId))
            ->select(
                'order_items.product_id',
                DB::raw('MAX(order_items.product_name) as product_name'),
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.line_total) as revenue')
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('qty')
            ->orderByDesc('revenue')
            ->get();

        $totalQty = (int) $rows->sum('qty');
        $totalRevenue = (float) $rows->sum('revenue');

        $products = $rows->values()->map(function ($row, $index) use ($totalQty, $totalRevenue) {
            $qty = (int) $row->qty;
            $revenue = (float) $row->revenue;

            return [
                'rank' => $index + 1,
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'qty' => $qty,
                'revenue' => $revenue,
                'qty_pct' => $totalQty > 0 ? round($qty / $totalQty * 100, 1) : 0,
                'revenue_pct' => $totalRevenue > 0 ? round($revenue / $totalRevenue * 100, 1) : 0,
            ];
        });

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('zone')
            ->orderBy('name')
            ->get(['id', 'name', 'zone', 'type', 'code']);

        return response()->json([
            'filters' => [
                'period' => $range->period,
                'days' => $range->days,
                'location_id' => $locationId,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
            ],
            'totals' => [
                'qty' => $totalQty,
                'revenue' => $totalRevenue,
            ],
            'products' => $products,
            'locations' => $locations,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $range = ReportDateRange::fromRequest($request);
        $locationId = $request->filled('location_id') ? $request->integer('location_id') : null;

        $from = $range->from;
        $to = $range->to;
        $days = $range->days;
        $today = now()->toDateString();

        $base = Order::query()
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        $period = (clone $base)->whereBetween('created_at', [$from, $to]);
        $periodActive = (clone $period)->where('status', '!=', 'cancelled');

        $todayQuery = (clone $base)->whereDate('created_at', $today);
        $todayActive = (clone $todayQuery)->where('status', '!=', 'cancelled');

        $ordersPeriod = (clone $period)->count();
        $ordersActive = (clone $periodActive)->count();
        $revenuePeriod = (float) (clone $periodActive)->sum('total');
        $paidPeriod = (float) (clone $periodActive)->where('payment_status', 'paid')->sum('total');
        $unpaidPeriod = (float) (clone $periodActive)->whereIn('payment_status', ['unpaid', 'pending'])->sum('total');
        $cancelledPeriod = (clone $period)->where('status', 'cancelled')->count();
        $avgTicket = $ordersActive > 0 ? round($revenuePeriod / $ordersActive, 2) : 0;

        $ordersToday = (clone $todayQuery)->count();
        $revenueToday = (float) (clone $todayActive)->sum('total');
        $paidToday = (float) (clone $todayActive)->where('payment_status', 'paid')->sum('total');

        $metrics = $this->buildPeriodMetrics($range, $locationId);

        $statusBreakdown = (clone $period)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $paymentBreakdown = (clone $periodActive)
            ->selectRaw('payment_method, payment_status, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('payment_method', 'payment_status')
            ->get();

        $paymentMethods = (clone $periodActive)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('payment_method')
            ->get();

        $lowStock = Product::query()
            ->where('track_inventory', true)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(20)
            ->get(['id', 'name', 'stock_quantity', 'low_stock_threshold', 'is_available']);

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('zone')
            ->orderBy('name')
            ->get(['id', 'name', 'zone', 'type', 'code']);

        return response()->json([
            'filters' => [
                'period' => $range->period,
                'days' => $days,
                'location_id' => $locationId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'kpis' => [
                'orders_period' => $ordersPeriod,
                'orders_active' => $ordersActive,
                'revenue_period' => $revenuePeriod,
                'paid_period' => $paidPeriod,
                'unpaid_period' => $unpaidPeriod,
                'cancelled_period' => $cancelledPeriod,
                'avg_ticket' => $avgTicket,
                'orders_today' => $ordersToday,
                'revenue_today' => $revenueToday,
                'paid_today' => $paidToday,
                'pending_orders' => (clone $base)->whereIn('status', ['received', 'accepted', 'preparing'])->count(),
                'ready_orders' => (clone $base)->where('status', 'ready')->count(),
                'open_waiter_calls' => WaiterCall::query()
                    ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                    ->whereIn('status', ['pending', 'acknowledged'])
                    ->count(),
            ],
            'orders_today' => $ordersToday,
            'revenue_today' => $revenueToday,
            'paid_today' => $paidToday,
            'avg_ticket' => $avgTicket,
            'pending_orders' => (clone $base)->whereIn('status', ['received', 'accepted', 'preparing'])->count(),
            'ready_orders' => (clone $base)->where('status', 'ready')->count(),
            'open_waiter_calls' => WaiterCall::query()
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                ->whereIn('status', ['pending', 'acknowledged'])
                ->count(),
            'products_count' => Product::query()->where('is_active', true)->count(),
            'top_products' => $metrics['top_products'],
            'revenue_by_day' => $metrics['revenue_by_day'],
            'orders_by_day' => $metrics['orders_by_day'],
            'orders_by_hour' => $metrics['orders_by_hour'],
            'status_breakdown' => $statusBreakdown,
            'payment_breakdown' => $paymentBreakdown,
            'payment_methods' => $paymentMethods,
            'by_location' => $metrics['by_location'],
            'locations' => $locations,
            'low_stock' => $lowStock,
        ]);
    }

    /** @return array<string, mixed> */
    private function buildPeriodMetrics(ReportDateRange $range, ?int $locationId): array
    {
        $from = $range->from;
        $to = $range->to;
        $days = $range->days;

        $base = Order::query()
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));

        $period = (clone $base)->whereBetween('created_at', [$from, $to]);
        $periodActive = (clone $period)->where('status', '!=', 'cancelled');

        $ordersActive = (clone $periodActive)->count();
        $revenuePeriod = (float) (clone $periodActive)->sum('total');
        $avgTicket = $ordersActive > 0 ? round($revenuePeriod / $ordersActive, 2) : 0;

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', TenantContext::id())
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('orders.location_id', $locationId))
            ->select(
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.line_total) as revenue')
            )
            ->groupBy('order_items.product_name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $rawByDay = (clone $periodActive)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $revenueByDay = [];
        $ordersByDay = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $row = $rawByDay->get($day);
            $orders = (int) ($row->orders ?? 0);
            $revenue = (float) ($row->revenue ?? 0);
            $revenueByDay[] = ['day' => $day, 'orders' => $orders, 'revenue' => $revenue];
            $ordersByDay[] = ['day' => $day, 'orders' => $orders];
        }

        $byLocation = Order::query()
            ->join('locations', 'locations.id', '=', 'orders.location_id')
            ->where('orders.tenant_id', TenantContext::id())
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->when($locationId, fn ($q) => $q->where('orders.location_id', $locationId))
            ->selectRaw('locations.id, locations.name, locations.zone, locations.type, COUNT(orders.id) as orders, SUM(orders.total) as revenue')
            ->groupBy('locations.id', 'locations.name', 'locations.zone', 'locations.type')
            ->orderByDesc('orders')
            ->limit(15)
            ->get();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $hourly = (clone $periodActive)
                ->selectRaw('EXTRACT(HOUR FROM created_at)::int as hour, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        } elseif ($driver === 'sqlite') {
            $hourly = (clone $periodActive)
                ->selectRaw("CAST(strftime('%H', created_at) AS INTEGER) as hour, COUNT(*) as orders, SUM(total) as revenue")
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        } else {
            $hourly = (clone $periodActive)
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }

        $hourlyMap = collect($hourly)->keyBy(fn ($row) => (int) $row->hour);
        $ordersByHour = [];
        for ($h = 0; $h < 24; $h++) {
            $row = $hourlyMap->get($h);
            $ordersByHour[] = [
                'hour' => $h,
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        }

        return [
            'orders_active' => $ordersActive,
            'revenue_period' => $revenuePeriod,
            'avg_ticket' => $avgTicket,
            'top_products' => $topProducts,
            'revenue_by_day' => $revenueByDay,
            'orders_by_day' => $ordersByDay,
            'by_location' => $byLocation,
            'orders_by_hour' => $ordersByHour,
        ];
    }
}
