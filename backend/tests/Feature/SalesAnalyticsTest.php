<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $tableA;

    private Location $tableB;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'currency' => 'EUR',
            'default_locale' => 'it',
        ]);

        $this->activateTenantSubscription($this->tenant);

        TenantContext::set($this->tenant);

        $this->tableA = Location::query()->create([
            'name' => 'Tavolo 1',
            'slug' => 'tavolo-1',
            'type' => 'table',
            'code' => 'table1',
            'zone' => 'A',
            'is_active' => true,
        ]);

        $this->tableB = Location::query()->create([
            'name' => 'Tavolo 2',
            'slug' => 'tavolo-2',
            'type' => 'table',
            'code' => 'table2',
            'zone' => 'B',
            'is_active' => true,
        ]);

        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->admin->markEmailAsVerified();

        $category = Category::query()->create([
            'name' => ['it' => 'Bevande'],
            'slug' => 'bevande',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'bar',
            'name' => ['it' => 'Spritz'],
            'slug' => 'spritz',
            'price' => 10,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other Beach',
            'slug' => 'other-beach',
            'is_active' => true,
        ]);

        $otherLocation = Location::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Table',
            'slug' => 'other-table',
            'type' => 'table',
            'code' => 'other1',
            'is_active' => true,
        ]);

        Order::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'order_number' => 'BO-OTHER',
            'location_id' => $otherLocation->id,
            'status' => 'delivered',
            'customer_session' => 'other',
            'subtotal' => 999,
            'total' => 999,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_can_load_sales_analytics_for_today(): void
    {
        $order = Order::query()->create([
            'order_number' => 'BO-1',
            'location_id' => $this->tableA->id,
            'status' => 'delivered',
            'customer_session' => 'sess-1',
            'subtotal' => 20,
            'total' => 20,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Spritz',
            'station' => 'bar',
            'unit_price' => 10,
            'quantity' => 2,
            'line_total' => 20,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/reports/sales?period=today')
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_revenue', 20)
            ->assertJsonPath('average_order_value', 20)
            ->assertJsonCount(1, 'best_selling_products')
            ->assertJsonPath('best_selling_products.0.product_name', 'Spritz')
            ->assertJsonCount(1, 'orders_by_table')
            ->assertJsonPath('orders_by_table.0.name', 'Tavolo 1');
    }

    public function test_yesterday_period_excludes_today_orders(): void
    {
        Order::query()->create([
            'order_number' => 'BO-TODAY',
            'location_id' => $this->tableA->id,
            'status' => 'delivered',
            'customer_session' => 'today',
            'subtotal' => 15,
            'total' => 15,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ]);

        Order::query()->create([
            'order_number' => 'BO-YDAY',
            'location_id' => $this->tableB->id,
            'status' => 'delivered',
            'customer_session' => 'yday',
            'subtotal' => 30,
            'total' => 30,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ])->forceFill([
            'created_at' => now()->subDay()->setTime(12, 0),
            'updated_at' => now()->subDay()->setTime(12, 0),
        ])->save();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/reports/sales?period=yesterday')
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_revenue', 30);
    }

    public function test_custom_date_range_filters_orders(): void
    {
        Order::query()->create([
            'order_number' => 'BO-OLD',
            'location_id' => $this->tableA->id,
            'status' => 'delivered',
            'customer_session' => 'old',
            'subtotal' => 10,
            'total' => 10,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ])->forceFill([
            'created_at' => now()->subDays(10)->setTime(10, 0),
            'updated_at' => now()->subDays(10)->setTime(10, 0),
        ])->save();

        Order::query()->create([
            'order_number' => 'BO-IN',
            'location_id' => $this->tableA->id,
            'status' => 'delivered',
            'customer_session' => 'in',
            'subtotal' => 25,
            'total' => 25,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ])->forceFill([
            'created_at' => now()->subDays(3)->setTime(10, 0),
            'updated_at' => now()->subDays(3)->setTime(10, 0),
        ])->save();

        Sanctum::actingAs($this->admin);

        $from = now()->subDays(5)->toDateString();
        $to = now()->subDays(1)->toDateString();

        $this->getJson("/api/admin/reports/sales?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_revenue', 25)
            ->assertJsonPath('filters.period', 'custom');
    }

    public function test_cancelled_orders_are_excluded_from_totals(): void
    {
        Order::query()->create([
            'order_number' => 'BO-CANCEL',
            'location_id' => $this->tableA->id,
            'status' => 'cancelled',
            'customer_session' => 'cancel',
            'subtotal' => 50,
            'total' => 50,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/reports/sales?period=today')
            ->assertOk()
            ->assertJsonPath('total_orders', 0)
            ->assertJsonPath('total_revenue', 0);
    }

    public function test_staff_without_orders_manage_cannot_access_sales_analytics(): void
    {
        $staff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff',
            'email' => 'staff@test.com',
            'password' => 'password',
            'role' => User::ROLE_STAFF,
            'is_active' => true,
        ]);
        $staff->markEmailAsVerified();

        Sanctum::actingAs($staff);

        $this->getJson('/api/admin/reports/sales?period=today')
            ->assertForbidden();
    }
}
