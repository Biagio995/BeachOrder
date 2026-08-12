<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PlatformOverviewController extends Controller
{
    /**
     * Cross-tenant performance overview for super_admin.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $days = min(90, max(1, (int) $request->query('days', 7)));
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        $tenants = Tenant::query()->with('subscription')->orderBy('name')->get();

        $orderStats = Order::withoutGlobalScopes()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                tenant_id,
                COUNT(*) as orders_total,
                SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as orders_active,
                SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END) as revenue,
                SUM(CASE WHEN status != 'cancelled' AND payment_status = 'paid' THEN total ELSE 0 END) as paid_revenue,
                MAX(created_at) as last_order_at
            ")
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $locationCounts = Location::withoutGlobalScopes()
            ->selectRaw('tenant_id, COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $userCounts = User::query()
            ->whereNotNull('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $rawByDay = Order::withoutGlobalScopes()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $revenueByDay = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $row = $rawByDay->get($day);
            $revenueByDay[] = [
                'day' => $day,
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        }

        $tenantRows = $tenants->map(function (Tenant $tenant) use ($orderStats, $locationCounts, $userCounts) {
            $orders = $orderStats->get($tenant->id);
            $locs = $locationCounts->get($tenant->id);
            $users = $userCounts->get($tenant->id);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'default_locale' => $tenant->default_locale,
                'currency' => $tenant->currency,
                'is_active' => $tenant->is_active,
                'admin_suspended' => $tenant->isAdminSuspended(),
                'subscription_status' => $tenant->subscription?->publicStatus() ?? Subscription::STATUS_INACTIVE,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'orders' => (int) ($orders->orders_active ?? 0),
                'orders_total' => (int) ($orders->orders_total ?? 0),
                'revenue' => round((float) ($orders->revenue ?? 0), 2),
                'paid_revenue' => round((float) ($orders->paid_revenue ?? 0), 2),
                'last_order_at' => $orders?->last_order_at
                    ? Carbon::parse($orders->last_order_at)->toIso8601String()
                    : null,
                'locations' => (int) ($locs->total ?? 0),
                'locations_active' => (int) ($locs->active ?? 0),
                'users' => (int) ($users->total ?? 0),
            ];
        })->values();

        $activeTenants = $tenantRows->where('is_active', true)->count();

        return response()->json([
            'filters' => [
                'days' => $days,
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'kpis' => [
                'tenants_total' => $tenantRows->count(),
                'tenants_active' => $activeTenants,
                'tenants_inactive' => $tenantRows->count() - $activeTenants,
                'orders' => (int) $tenantRows->sum('orders'),
                'revenue' => round((float) $tenantRows->sum('revenue'), 2),
                'paid_revenue' => round((float) $tenantRows->sum('paid_revenue'), 2),
                'locations' => (int) $tenantRows->sum('locations'),
                'users' => (int) $tenantRows->sum('users'),
            ],
            'revenue_by_day' => $revenueByDay,
            'tenants' => $tenantRows->sortByDesc('revenue')->values(),
        ]);
    }
}
