<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private Product $product;

    private User $barStaff;

    private User $kitchenStaff;

    private User $waiterStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Test Beach',
            'slug' => 'test-beach',
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'default_locale' => 'it',
            'settings' => [
                'loyalty_enabled' => true,
                'online_payments_enabled' => true,
            ],
            'is_active' => true,
        ]);

        TenantContext::set($this->tenant);

        $this->location = Location::query()->create([
            'name' => 'Ombrellone 1',
            'slug' => 'umb-1',
            'type' => 'umbrella',
            'code' => 'umbrella99',
            'zone' => 'A',
            'capacity' => 2,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => ['it' => 'Bevande', 'en' => 'Drinks'],
            'slug' => 'bevande',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'bar',
            'name' => ['it' => 'Spritz', 'en' => 'Spritz'],
            'slug' => 'spritz',
            'price' => 8.5,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
            'track_inventory' => true,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
        ]);

        $this->barStaff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bar Staff',
            'email' => 'bar@test.beach',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_BAR,
            'is_active' => true,
        ]);
        $this->barStaff->markEmailAsVerified();

        $this->kitchenStaff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@test.beach',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_KITCHEN,
            'is_active' => true,
        ]);
        $this->kitchenStaff->markEmailAsVerified();

        $this->waiterStaff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Waiter Staff',
            'email' => 'waiter@test.beach',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_WAITER,
            'is_active' => true,
        ]);
        $this->waiterStaff->markEmailAsVerified();

        TenantContext::clear();

        $this->activateTenantSubscription($this->tenant);
    }

    /**
     * @return array{customer_session: string, access_token: string}
     */
    private function claimAccess(?string $session = null): array
    {
        $session = $session ?: (string) Str::uuid();

        $claim = $this->postJson('/api/t/test-beach/locations/code/umbrella99/claim', [
            'customer_session' => $session,
        ])->assertOk();

        return [
            'customer_session' => $session,
            'access_token' => $claim->json('access_token'),
        ];
    }

    public function test_customer_can_create_and_track_order(): void
    {
        $access = $this->claimAccess();

        $create = $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'payment_method' => 'pay_at_location',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $create->assertCreated();
        $orderId = $create->json('id');

        $this->assertSame(8, $this->product->fresh()->stock_quantity);

        $this->getJson("/api/t/test-beach/orders/{$orderId}?session={$access['customer_session']}")
            ->assertOk()
            ->assertJsonPath('status', 'received');

        $this->getJson("/api/t/test-beach/orders/{$orderId}?session=" . Str::uuid())
            ->assertForbidden();
    }

    public function test_inventory_restored_on_cancel(): void
    {
        $access = $this->claimAccess();

        $orderId = $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
        ])->json('id');

        Sanctum::actingAs($this->waiterStaff);

        $this->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    public function test_role_based_status_transitions(): void
    {
        $access = $this->claimAccess();
        $orderId = $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->json('id');

        Sanctum::actingAs($this->waiterStaff);
        $this->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'accepted'])
            ->assertStatus(422);

        Sanctum::actingAs($this->barStaff);
        $this->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'accepted', 'station' => 'bar'])
            ->assertOk();

        $this->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'preparing', 'station' => 'bar'])
            ->assertOk();

        $this->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'ready', 'station' => 'bar'])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('bar_status', 'ready');

        Sanctum::actingAs($this->waiterStaff);
        $this->withHeader('X-Tenant', 'test-beach')
            ->patchJson("/api/orders/{$orderId}/status", ['status' => 'delivering'])
            ->assertOk();
    }

    public function test_mixed_order_ready_only_when_both_stations_done(): void
    {
        TenantContext::set($this->tenant);
        $foodCategory = Category::query()->create([
            'name' => ['it' => 'Cibo'],
            'slug' => 'cibo',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $food = Product::query()->create([
            'category_id' => $foodCategory->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Toast'],
            'slug' => 'toast',
            'price' => 5,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        TenantContext::clear();

        $access = $this->claimAccess();
        $orderId = $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $food->id, 'quantity' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('kitchen_status', 'received')
            ->assertJsonPath('bar_status', 'received')
            ->json('id');

        Sanctum::actingAs($this->barStaff);
        foreach (['accepted', 'preparing', 'ready'] as $status) {
            $this->withHeader('X-Tenant', 'test-beach')
                ->patchJson("/api/orders/{$orderId}/status", ['status' => $status, 'station' => 'bar'])
                ->assertOk();
        }

        $this->getJson("/api/t/test-beach/orders/{$orderId}?session={$access['customer_session']}")
            ->assertOk()
            ->assertJsonPath('status', 'preparing')
            ->assertJsonPath('bar_status', 'ready')
            ->assertJsonPath('kitchen_status', 'received');

        Sanctum::actingAs($this->kitchenStaff);
        foreach (['accepted', 'preparing', 'ready'] as $status) {
            $this->withHeader('X-Tenant', 'test-beach')
                ->patchJson("/api/orders/{$orderId}/status", ['status' => $status, 'station' => 'kitchen'])
                ->assertOk();
        }

        $this->assertSame('ready', Order::query()->find($orderId)->status);
    }

    public function test_station_boards_filter_items(): void
    {
        TenantContext::set($this->tenant);
        $foodCategory = Category::query()->create([
            'name' => ['it' => 'Cibo'],
            'slug' => 'cibo-board',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $food = Product::query()->create([
            'category_id' => $foodCategory->id,
            'station' => 'kitchen',
            'name' => ['it' => 'Pasta'],
            'slug' => 'pasta',
            'price' => 7,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        TenantContext::clear();

        $access = $this->claimAccess();
        $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $food->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        Sanctum::actingAs($this->kitchenStaff);
        $kitchenItems = $this->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/orders?station=kitchen&status=received')
            ->assertOk()
            ->json('data.0.items');
        $this->assertCount(1, $kitchenItems);
        $this->assertSame('kitchen', $kitchenItems[0]['station']);

        Sanctum::actingAs($this->barStaff);
        $barItems = $this->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/orders?station=bar&status=received')
            ->assertOk()
            ->json('data.0.items');
        $this->assertCount(1, $barItems);
        $this->assertSame('bar', $barItems[0]['station']);
    }

    public function test_waiter_board_keeps_delivered_unpaid_orders(): void
    {
        TenantContext::set($this->tenant);

        $order = Order::query()->create([
            'location_id' => $this->location->id,
            'order_number' => 'BO-TEST-1',
            'status' => 'delivered',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 8.5,
            'total' => 8.5,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
            'delivered_at' => now(),
        ]);

        $paid = Order::query()->create([
            'location_id' => $this->location->id,
            'order_number' => 'BO-TEST-2',
            'status' => 'delivered',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 8.5,
            'total' => 8.5,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'paid',
            'delivered_at' => now(),
        ]);

        TenantContext::clear();

        Sanctum::actingAs($this->waiterStaff);
        $waiterIds = $this->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/orders?status=ready,delivering')
            ->assertOk()
            ->json('data');
        $waiterIds = collect($waiterIds)->pluck('id');

        $this->assertTrue($waiterIds->contains($order->id));
        $this->assertFalse($waiterIds->contains($paid->id));

        $admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.beach',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $admin->markEmailAsVerified();

        Sanctum::actingAs($admin);
        $adminIds = $this->withHeader('X-Tenant', 'test-beach')
            ->getJson('/api/orders?status=ready,delivering')
            ->assertOk()
            ->json('data');
        $adminIds = collect($adminIds)->pluck('id');

        $this->assertTrue($adminIds->contains($order->id));
        $this->assertFalse($adminIds->contains($paid->id));
    }

    public function test_tenant_isolation_on_menu_products(): void
    {
        $other = Tenant::query()->create([
            'name' => 'Other',
            'slug' => 'other-beach',
            'timezone' => 'UTC',
            'currency' => 'EUR',
            'default_locale' => 'en',
            'settings' => [],
            'is_active' => true,
        ]);

        TenantContext::set($other);
        $foreignCategory = Category::query()->create([
            'name' => ['en' => 'Other'],
            'slug' => 'other',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $foreignProduct = Product::query()->create([
            'category_id' => $foreignCategory->id,
            'station' => 'bar',
            'name' => ['en' => 'Secret'],
            'slug' => 'secret',
            'price' => 1,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        TenantContext::clear();

        $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'customer_session' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $foreignProduct->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_online_payments_can_be_disabled(): void
    {
        $this->tenant->update([
            'settings' => [
                'loyalty_enabled' => true,
                'online_payments_enabled' => false,
            ],
        ]);

        $this->postJson('/api/t/test-beach/orders', [
            'location_code' => 'umbrella99',
            'customer_session' => (string) Str::uuid(),
            'payment_method' => 'card_online',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }
}
