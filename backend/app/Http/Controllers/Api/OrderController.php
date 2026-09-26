<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use App\Services\LocationAccessService;
use App\Services\PaymentService;
use App\Services\Pos\PosOrderSyncService;
use App\Services\Printing\PrintService;
use App\Services\ProductCustomizationService;
use App\Services\StripeOrderPaymentService;
use App\Support\LocalizedText;
use App\Support\OrderNumber;
use App\Support\RolePermissions;
use App\Support\TenantContext;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private PaymentService $payments,
        private LocationAccessService $locationAccess,
        private ProductCustomizationService $customizations,
        private PrintService $printing,
        private PosOrderSyncService $posSync,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_code' => ['required', 'string'],
            'access_token' => ['required', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_session' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'locale' => ['nullable', 'string', 'in:it,en,el,de'],
            'payment_method' => ['nullable', 'string', 'in:' . implode(',', PaymentService::METHODS)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', TenantRules::exists('products')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.variant_option_ids' => ['nullable', 'array', 'max:20'],
            'items.*.variant_option_ids.*' => ['integer', TenantRules::exists('product_variant_options')],
            'items.*.addons' => ['nullable', 'array', 'max:40'],
            'items.*.addons.*.id' => ['required_with:items.*.addons', 'integer', TenantRules::exists('product_addons')],
            'items.*.addons.*.quantity' => ['required_with:items.*.addons', 'integer', 'min:1', 'max:99'],
            // Legacy: treat each id as quantity 1
            'items.*.addon_ids' => ['nullable', 'array', 'max:30'],
            'items.*.addon_ids.*' => ['integer', TenantRules::exists('product_addons')],
        ]);

        $tenant = TenantContext::get();
        $method = $data['payment_method'] ?? 'pay_at_location';

        $location = Location::query()
            ->where('code', $data['location_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $this->inventory->assertAvailable($data['items']);

        $locale = $data['locale'] ?? $tenant?->default_locale ?? 'it';

        $order = DB::transaction(function () use ($data, $location, $method, $locale) {
            $access = $this->locationAccess->assertValid(
                $data['access_token'],
                $location,
                $data['customer_session'],
                lock: true,
            );

            $this->inventory->decrement($data['items']);

            $subtotal = 0;
            $lineItems = [];
            $hasKitchen = false;
            $hasBar = false;

            foreach ($data['items'] as $item) {
                $product = Product::query()
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                $station = in_array($product->station, Order::STATIONS, true)
                    ? $product->station
                    : 'kitchen';

                if ($station === 'bar') {
                    $hasBar = true;
                } else {
                    $hasKitchen = true;
                }

                $addonSelections = $item['addons'] ?? [];
                if ($addonSelections === [] && ! empty($item['addon_ids'])) {
                    $addonSelections = array_map(
                        fn($id) => ['id' => (int) $id, 'quantity' => 1],
                        $item['addon_ids']
                    );
                }

                $resolved = $this->customizations->resolveLine(
                    $product,
                    $item['variant_option_ids'] ?? [],
                    $addonSelections,
                    $locale,
                );

                $unitPrice = (float) $product->price + $resolved['unit_extra'];
                $lineTotal = $unitPrice * (int) $item['quantity'];
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'station' => $station,
                    'product_name' => LocalizedText::resolveOn($product, 'name', $locale) ?? $product->translatedName($locale),
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                    'variants' => $resolved['variants'] !== [] ? $resolved['variants'] : null,
                    'addons' => $resolved['addons'] !== [] ? $resolved['addons'] : null,
                    'line_total' => $lineTotal,
                ];
            }

            $order = Order::create([
                'order_number' => OrderNumber::generate(),
                'location_id' => $location->id,
                'status' => 'received',
                'kitchen_status' => $hasKitchen ? 'received' : null,
                'bar_status' => $hasBar ? 'received' : null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_session' => $data['customer_session'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'payment_method' => $method,
                'payment_status' => in_array($method, PaymentService::ONLINE_METHODS, true) ? 'pending' : 'unpaid',
                'payment_reference' => 'BO-PAY-' . Str::upper(Str::random(8)),
            ]);

            $order->items()->createMany($lineItems);
            $this->locationAccess->consume($access, $order);

            return $order->load(['items', 'location']);
        });

        $response = $order->toArray();

        try {
            event(new OrderUpdated($order));
        } catch (\Throwable) {
            // Realtime is best-effort; order persistence must not fail.
        }

        try {
            $this->printing->dispatchOrderPrint($order);
        } catch (\Throwable) {
            // Printing is best-effort; order persistence must not fail.
        }

        AuditLogger::log('order.created', $order, null, $order->toArray());

        if (! in_array($order->payment_method, PaymentService::ONLINE_METHODS, true)) {
            try {
                $this->posSync->queueForOrder($order);
            } catch (\Throwable) {
                // POS sync is best-effort; order persistence must not fail.
            }
        }

        return response()->json($response, 201);
    }

    public function show(Request $request, string $tenant, Order $order): JsonResponse
    {
        $order->load(['items', 'location']);

        $session = $request->query('session');
        if ($session && $order->customer_session && hash_equals($order->customer_session, (string) $session)) {
            if (in_array($order->payment_method, PaymentService::STRIPE_METHODS, true)) {
                app(StripeOrderPaymentService::class)->syncPaymentStatus($order);
                $order->refresh();
            }

            $payload = $order->toArray();
            $payload['payment_receipt'] = null;

            return response()->json($payload);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with(['items', 'location'])->latest();

        // Hosted-checkout orders reach kitchen/bar only after payment is confirmed.
        $query->where(function ($builder) {
            $builder->whereNotIn('payment_method', PaymentService::ONLINE_METHODS)
                ->orWhere('payment_status', 'paid');
        });

        $requested = [];
        if ($status = $request->query('status')) {
            $requested = array_values(array_filter(array_map('trim', explode(',', (string) $status))));
        }

        $user = $request->user();
        $waiterActive = ['ready', 'delivering'];
        $station = $request->query('station');
        if ($station && ! in_array($station, Order::STATIONS, true)) {
            $station = null;
        }

        if ($denied = $this->enforceStaffStationAccess($user, $station)) {
            return $denied;
        }

        $hasOrdersManage = $user && RolePermissions::userHas($user, RolePermissions::ORDERS_MANAGE);

        // Waiter-style board keeps delivered+unpaid visible for payment collection.
        $onWaiterBoard = $user && (
            (
                RolePermissions::userHas($user, RolePermissions::WAITER_CALLS_MANAGE)
                && ! $hasOrdersManage
            )
            || (
                $hasOrdersManage
                && $requested !== []
                && array_diff($requested, $waiterActive) === []
                && ! $station
            )
        ) && (
            $requested !== []
            && array_diff($requested, $waiterActive) === []
            && ! $station
        );

        if ($onWaiterBoard) {
            $active = $requested
                ? array_values(array_intersect($requested, $waiterActive))
                : $waiterActive;

            $query->where(function ($builder) use ($active) {
                if ($active !== []) {
                    $builder->whereIn('status', $active);
                }

                $builder->orWhere(function ($inner) {
                    $inner->where('status', 'delivered')
                        ->whereIn('payment_status', ['unpaid', 'pending']);
                });
            });
        } elseif ($station) {
            $resolvedStation = $station;
            if (! $resolvedStation) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            $column = $resolvedStation === 'bar' ? 'bar_status' : 'kitchen_status';
            $allowed = Order::STATION_STATUSES;
            $statuses = $requested
                ? array_values(array_intersect($requested, $allowed))
                : ['received', 'accepted', 'preparing'];

            $query->whereNotNull($column)
                ->whereIn($column, $statuses ?: ['__none__']);
        } elseif ($requested) {
            $query->whereIn('status', $requested);
        }

        if ($payment = $request->query('payment_status')) {
            $query->whereIn('payment_status', array_map('trim', explode(',', (string) $payment)));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        $paginator = $query->paginate(50);

        if ($station) {
            $resolvedStation = $station;
            $paginator->getCollection()->transform(function (Order $order) use ($resolvedStation) {
                $order->setRelation(
                    'items',
                    $order->items->where('station', $resolvedStation)->values()
                );
                $order->setAttribute('station_status', $order->stationStatus($resolvedStation));

                return $order;
            });
        }

        return response()->json($paginator);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', Order::STATUSES)],
            'station' => ['nullable', 'string', 'in:' . implode(',', Order::STATIONS)],
        ]);

        $user = $request->user();

        if (! empty($data['station']) || ($user->isStaffRole() && in_array($data['status'], Order::STATION_STATUSES, true))) {
            $station = $data['station'] ?? $request->query('station');

            if (! $station && $user->isStaffRole()) {
                return response()->json(['message' => 'Station required'], 422);
            }

            if (! $station) {
                return response()->json(['message' => 'Station required'], 422);
            }

            if (! $order->canRoleUpdateStation($user, $station, $data['status'])) {
                return response()->json([
                    'message' => "Cannot transition {$station} from {$order->stationStatus($station)} to {$data['status']}",
                ], 422);
            }

            $old = [
                'status' => $order->status,
                'kitchen_status' => $order->kitchen_status,
                'bar_status' => $order->bar_status,
            ];

            $order->applyStationStatus($station, $data['status'], $user);
            $order->save();
            $order->load(['items', 'location']);

            try {
                event(new OrderUpdated($order));
            } catch (\Throwable) {
                //
            }

            AuditLogger::log('order.station_status_changed', $order, $old, [
                'status' => $order->status,
                'kitchen_status' => $order->kitchen_status,
                'bar_status' => $order->bar_status,
                'station' => $station,
            ]);

            $order->setRelation(
                'items',
                $order->items->where('station', $station)->values()
            );
            $order->setAttribute('station_status', $order->stationStatus($station));

            return response()->json($order);
        }

        if (! $order->canRoleTransitionTo($user, $data['status'])) {
            return response()->json([
                'message' => "Cannot transition from {$order->status} to {$data['status']}",
            ], 422);
        }

        $old = $order->status;

        $order->status = $data['status'];

        match ($data['status']) {
            'accepted' => tap($order, function (Order $o) use ($user) {
                $o->accepted_by = $user->id;
                $o->accepted_at = now();
            }),
            'ready' => $order->ready_at = now(),
            'delivered' => tap($order, function (Order $o) use ($user) {
                $o->delivered_by = $user->id;
                $o->delivered_at = now();
            }),
            'cancelled' => $order->cancelled_at = now(),
            default => null,
        };

        // Keep station mirrors in sync when admin forces overall status / cancel.
        if ($data['status'] === 'cancelled') {
            if ($order->kitchen_status !== null) {
                $order->kitchen_status = null;
            }
            if ($order->bar_status !== null) {
                $order->bar_status = null;
            }
        } elseif (in_array($data['status'], Order::STATION_STATUSES, true) && ($user->isAdmin() || $user->isSuperAdmin())) {
            if ($order->kitchen_status !== null) {
                $order->kitchen_status = $data['status'];
            }
            if ($order->bar_status !== null) {
                $order->bar_status = $data['status'];
            }
            if ($data['status'] === 'ready') {
                $order->ready_at = $order->ready_at ?? now();
            }
        }

        $order->save();

        if ($order->status === 'cancelled' && $old !== 'cancelled') {
            $this->inventory->restoreForOrder($order);
        }

        $order->load(['items', 'location']);

        try {
            event(new OrderUpdated($order));
        } catch (\Throwable) {
            // Realtime is best-effort.
        }
        AuditLogger::log('order.status_changed', $order, ['status' => $old], ['status' => $order->status]);

        return response()->json($order);
    }

    public function updatePayment(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', 'in:unpaid,pending,paid,failed,refunded'],
        ]);

        if (! $this->payments->canStaffSetPaymentStatus($order, $data['payment_status'])) {
            return response()->json([
                'message' => 'Online order payments must be confirmed by the payment provider.',
            ], 422);
        }

        $old = $order->payment_status;
        $order->payment_status = $data['payment_status'];

        if ($data['payment_status'] === 'paid' && ! $order->paid_at) {
            $order->paid_at = now();
        }

        $order->save();
        $order->load(['items', 'location']);

        AuditLogger::log('order.payment_changed', $order, ['payment_status' => $old], ['payment_status' => $order->payment_status]);

        try {
            event(new OrderUpdated($order));
        } catch (\Throwable) {
            //
        }

        return response()->json($order);
    }

    private function enforceStaffStationAccess(?User $user, ?string &$station): ?JsonResponse
    {
        if (! $user?->isStaffRole()) {
            return null;
        }

        $position = $user->staffPosition();
        if ($position === null) {
            return response()->json(['message' => 'Staff position not assigned'], 403);
        }

        if (in_array($position, [User::STAFF_POSITION_KITCHEN, User::STAFF_POSITION_BAR], true)) {
            if ($station !== null && $station !== $position) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $station = $position;

            return null;
        }

        if ($position === User::STAFF_POSITION_WAITER && $station !== null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }
}
