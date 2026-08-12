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

/**
 * US-13 — Product analytics.
 */
class ProductAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_ranked_products_with_percentages(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
            'currency' => 'EUR',
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        $this->activateTenantSubscription($tenant);

        TenantContext::set($tenant);

        $category = Category::query()->create([
            'name' => ['it' => 'Bevande'],
            'slug' => 'bevande',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $espresso = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'bar',
            'name' => ['it' => 'Espresso'],
            'slug' => 'espresso',
            'price' => 2,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $spritz = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'bar',
            'name' => ['it' => 'Spritz'],
            'slug' => 'spritz',
            'price' => 8,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 2,
        ]);

        $location = Location::query()->create([
            'name' => 'Ombrellone 1',
            'slug' => 'ombrellone-1',
            'type' => 'umbrella',
            'code' => 'umbrella1',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'BO-1',
            'location_id' => $location->id,
            'status' => 'delivered',
            'customer_session' => 'sess-1',
            'subtotal' => 36,
            'total' => 36,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $espresso->id,
            'station' => 'bar',
            'product_name' => 'Espresso',
            'unit_price' => 2,
            'quantity' => 10,
            'line_total' => 20,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $spritz->id,
            'station' => 'bar',
            'product_name' => 'Spritz',
            'unit_price' => 8,
            'quantity' => 2,
            'line_total' => 16,
        ]);

        Sanctum::actingAs($admin);

        $this->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/reports/products?period=7d')
            ->assertOk()
            ->assertJsonPath('totals.qty', 12)
            ->assertJsonPath('totals.revenue', 36)
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.rank', 1)
            ->assertJsonPath('products.0.product_name', 'Espresso')
            ->assertJsonPath('products.0.qty', 10)
            ->assertJsonPath('products.0.revenue', 20)
            ->assertJsonPath('products.0.qty_pct', 83.3)
            ->assertJsonPath('products.0.revenue_pct', 55.6)
            ->assertJsonPath('products.1.rank', 2)
            ->assertJsonPath('products.1.product_name', 'Spritz')
            ->assertJsonPath('products.1.qty', 2)
            ->assertJsonPath('products.1.qty_pct', 16.7)
            ->assertJsonPath('products.1.revenue_pct', 44.4);
    }

    public function test_cancelled_orders_are_excluded_from_product_analytics(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Azure Beach',
            'slug' => 'azure-beach',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        $this->activateTenantSubscription($tenant);

        TenantContext::set($tenant);

        $category = Category::query()->create([
            'name' => ['it' => 'Cat'],
            'slug' => 'cat',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Pizza'],
            'slug' => 'pizza',
            'price' => 12,
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $location = Location::query()->create([
            'name' => 'Tavolo 1',
            'slug' => 'tavolo-1',
            'type' => 'table',
            'code' => 'table1',
            'is_active' => true,
        ]);

        $cancelled = Order::query()->create([
            'order_number' => 'BO-C',
            'location_id' => $location->id,
            'status' => 'cancelled',
            'customer_session' => 'sess-c',
            'subtotal' => 12,
            'total' => 12,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        OrderItem::query()->create([
            'order_id' => $cancelled->id,
            'product_id' => $product->id,
            'station' => 'kitchen',
            'product_name' => 'Pizza',
            'unit_price' => 12,
            'quantity' => 1,
            'line_total' => 12,
        ]);

        Sanctum::actingAs($admin);

        $this->withHeader('X-Tenant', 'azure-beach')
            ->getJson('/api/admin/reports/products?period=7d')
            ->assertOk()
            ->assertJsonPath('totals.qty', 0)
            ->assertJsonPath('totals.revenue', 0)
            ->assertJsonCount(0, 'products');
    }
}
