<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderPrintLog;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Printing\PrintOrderStationJob;
use App\Services\Printing\PrintService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class KitchenPrintingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private Product $product;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Print Beach',
            'slug' => 'print-beach',
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'default_locale' => 'it',
            'settings' => Tenant::defaultSettings([
                'printing' => [
                    'enabled' => true,
                    'driver' => 'escpos_tcp',
                    'stations' => [
                        'kitchen' => ['host' => '192.168.1.50', 'port' => 9100, 'copies' => 1],
                        'bar' => ['host' => null, 'port' => 9100, 'copies' => 1],
                    ],
                ],
            ]),
            'is_active' => true,
        ]);

        TenantContext::set($this->tenant);
        $this->activateTenantSubscription($this->tenant);

        $this->location = Location::query()->create([
            'name' => 'Tavolo 3',
            'slug' => 'table-3',
            'type' => 'table',
            'code' => 'table3',
            'zone' => 'B',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => ['it' => 'Cucina'],
            'slug' => 'cucina',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Pasta al pomodoro'],
            'slug' => 'pasta',
            'price' => 12,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
            'track_inventory' => false,
        ]);

        $this->staff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chef',
            'email' => 'chef@print.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_KITCHEN,
            'is_active' => true,
        ]);
        $this->staff->markEmailAsVerified();

        TenantContext::clear();
    }

    public function test_dispatches_print_job_on_new_order_when_printing_enabled(): void
    {
        Queue::fake();

        $session = (string) Str::uuid();
        $token = $this->claimAccess($session);

        $this->postJson("/api/t/{$this->tenant->slug}/orders", [
            'location_code' => $this->location->code,
            'access_token' => $token,
            'customer_session' => $session,
            'payment_method' => 'pay_at_location',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])->assertCreated();

        Queue::assertPushed(PrintOrderStationJob::class, function (PrintOrderStationJob $job) {
            return $job->station === 'kitchen' && $job->reprint === false;
        });
    }

    public function test_prints_unpaid_card_pos_orders(): void
    {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'BO-ONLINE-1',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 12,
            'total' => 12,
            'payment_method' => 'card_online',
            'payment_status' => 'unpaid',
        ]);
        $order->setRelation('tenant', $this->tenant);

        $this->assertTrue(app(PrintService::class)->shouldPrintOrder($order));
    }

    public function test_reprint_endpoint_calls_print_service(): void
    {
        Sanctum::actingAs($this->staff);

        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'BO-TEST-12345',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'bar_status' => null,
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 12,
            'total' => 12,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Pasta al pomodoro',
            'quantity' => 1,
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        $log = OrderPrintLog::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'station' => 'kitchen',
            'driver' => 'escpos_tcp',
            'status' => OrderPrintLog::STATUS_SUCCESS,
            'copies' => 1,
            'is_reprint' => true,
            'printed_at' => now(),
        ]);

        $mock = Mockery::mock(PrintService::class);
        $mock->shouldReceive('printStation')
            ->once()
            ->with(Mockery::type(Order::class), 'kitchen', true)
            ->andReturn($log);
        $this->app->instance(PrintService::class, $mock);

        $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson("/api/orders/{$order->id}/print", ['station' => 'kitchen'])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'success');
    }

    public function test_reprint_returns_error_when_print_fails(): void
    {
        Sanctum::actingAs($this->staff);

        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'BO-FAIL-99999',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 12,
            'total' => 12,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Pasta',
            'quantity' => 1,
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        $mock = Mockery::mock(PrintService::class);
        $mock->shouldReceive('printStation')
            ->once()
            ->andThrow(new \RuntimeException('Cannot connect to printer'));
        $this->app->instance(PrintService::class, $mock);

        $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson("/api/orders/{$order->id}/print", ['station' => 'kitchen'])
            ->assertStatus(502)
            ->assertJsonPath('results.0.error', 'Cannot connect to printer');
    }

    public function test_print_preview_endpoint(): void
    {
        $admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@print.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        Sanctum::actingAs($admin);

        $this->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson('/api/admin/settings/printing/preview?station=kitchen')
            ->assertOk()
            ->assertJsonStructure(['preview']);
    }

    public function test_reprint_forbidden_for_staff_without_staff_position(): void
    {
        $staff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Positionless Staff',
            'email' => 'positionless@print.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => null,
            'is_active' => true,
        ]);
        $staff->markEmailAsVerified();

        Sanctum::actingAs($staff);

        $order = $this->createKitchenOrder('BO-NOPOS-1');

        $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson("/api/orders/{$order->id}/print", ['station' => 'kitchen'])
            ->assertForbidden();
    }

    public function test_reprint_not_found_for_staff_from_another_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Beach',
            'slug' => 'other-beach',
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'default_locale' => 'it',
            'settings' => Tenant::defaultSettings(),
            'is_active' => true,
        ]);
        $this->activateTenantSubscription($otherTenant);

        $otherStaff = User::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Kitchen',
            'email' => 'other-kitchen@print.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_KITCHEN,
            'is_active' => true,
        ]);
        $otherStaff->markEmailAsVerified();

        Sanctum::actingAs($otherStaff);

        $order = $this->createKitchenOrder('BO-XTenant-1');

        // Current behavior: tenant.user binds the caller's tenant, so BelongsToTenant
        // scopes the Order route model to zero rows → 404 (not 403).
        $this->withHeader('X-Tenant', $otherTenant->slug)
            ->postJson("/api/orders/{$order->id}/print", ['station' => 'kitchen'])
            ->assertNotFound();
    }

    public function test_reprint_allowed_for_waiter_staff_on_kitchen_order(): void
    {
        $waiter = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Waiter',
            'email' => 'waiter@print.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_WAITER,
            'is_active' => true,
        ]);
        $waiter->markEmailAsVerified();

        Sanctum::actingAs($waiter);

        $order = $this->createKitchenOrder('BO-WAITER-1');
        $this->mockSuccessfulKitchenReprint();

        // Current behavior: waiter holds orders.update_status; print has no station gate → 200.
        $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson("/api/orders/{$order->id}/print", ['station' => 'kitchen'])
            ->assertOk();
    }

    public function test_reprint_allowed_for_bar_staff_on_kitchen_order(): void
    {
        $bar = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bar Staff',
            'email' => 'bar@print.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_BAR,
            'is_active' => true,
        ]);
        $bar->markEmailAsVerified();

        Sanctum::actingAs($bar);

        $order = $this->createKitchenOrder('BO-BAR-1');
        $this->mockSuccessfulKitchenReprint();

        // Current behavior: bar holds orders.update_status; print has no station gate → 200.
        $this->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson("/api/orders/{$order->id}/print", ['station' => 'kitchen'])
            ->assertOk();
    }

    private function createKitchenOrder(string $orderNumber): Order
    {
        $order = Order::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_number' => $orderNumber,
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'bar_status' => null,
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 12,
            'total' => 12,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Pasta al pomodoro',
            'quantity' => 1,
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        return $order;
    }

    private function mockSuccessfulKitchenReprint(): void
    {
        $log = new OrderPrintLog([
            'station' => 'kitchen',
            'driver' => 'escpos_tcp',
            'status' => OrderPrintLog::STATUS_SUCCESS,
            'copies' => 1,
            'is_reprint' => true,
            'printed_at' => now(),
        ]);

        $mock = Mockery::mock(PrintService::class);
        $mock->shouldReceive('printStation')
            ->once()
            ->with(Mockery::type(Order::class), 'kitchen', true)
            ->andReturn($log);
        $this->app->instance(PrintService::class, $mock);
    }

    private function claimAccess(string $session): string
    {
        $response = $this->postJson("/api/t/{$this->tenant->slug}/locations/code/{$this->location->code}/claim", [
            'customer_session' => $session,
        ]);

        return $response->json('access_token');
    }
}
