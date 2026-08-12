<?php

namespace Tests\Feature;

use App\Jobs\SyncOrderToPosJob;
use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\PosIntegration;
use App\Models\PosMapping;
use App\Models\PosOrderSync;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pos\PosOrderSyncService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Location $location;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Beach POS',
            'slug' => 'beach-pos',
            'timezone' => 'Europe/Athens',
            'currency' => 'EUR',
            'default_locale' => 'el',
            'settings' => Tenant::defaultSettings(),
            'is_active' => true,
        ]);

        $this->activateTenantSubscription($this->tenant);
        TenantContext::set($this->tenant);

        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@beach-pos.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->markEmailAsVerified();

        $this->location = Location::query()->create([
            'name' => 'Table 12',
            'slug' => 'table-12',
            'type' => 'table',
            'code' => 'table12',
            'zone' => 'A',
            'capacity' => 4,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => ['en' => 'Food'],
            'slug' => 'food',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'kitchen',
            'name' => ['en' => 'Margherita'],
            'slug' => 'margherita',
            'price' => 9.5,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        TenantContext::clear();
    }

    public function test_admin_can_configure_pos_integration(): void
    {
        $token = $this->admin->issueStaffToken();

        $response = $this->withToken($token)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->putJson('/api/admin/pos-integration', [
                'provider' => 'epsilon_pylon',
                'is_enabled' => true,
                'store_location_id' => 'STORE-42',
                'credentials' => ['stub_mode' => true],
            ]);

        $response->assertOk()
            ->assertJsonPath('provider', 'epsilon_pylon')
            ->assertJsonPath('is_enabled', true)
            ->assertJsonPath('store_location_id', 'STORE-42');

        $this->assertDatabaseHas('pos_integrations', [
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'store_location_id' => 'STORE-42',
        ]);
    }

    public function test_test_connection_sets_connected_in_stub_mode(): void
    {
        $token = $this->admin->issueStaffToken();

        PosIntegration::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'is_enabled' => true,
            'credentials' => ['stub_mode' => true],
            'connection_status' => 'disconnected',
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson('/api/admin/pos-integration/test');

        $response->assertOk()
            ->assertJsonPath('connection_status', 'connected');
    }

    public function test_order_queues_pos_sync_when_integration_enabled(): void
    {
        Queue::fake();
        TenantContext::set($this->tenant);

        $integration = PosIntegration::query()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'is_enabled' => true,
            'connection_status' => 'connected',
            'credentials' => ['stub_mode' => true],
        ]);

        PosMapping::query()->create([
            'tenant_id' => $this->tenant->id,
            'pos_integration_id' => $integration->id,
            'entity_type' => 'product',
            'local_id' => $this->product->id,
            'external_id' => 'ART-00123',
        ]);

        $order = Order::query()->create([
            'order_number' => 'BO-TEST-001',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 9.5,
            'total' => 9.5,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Margherita',
            'unit_price' => 9.5,
            'quantity' => 1,
            'line_total' => 9.5,
        ]);

        app(PosOrderSyncService::class)->queueForOrder($order->fresh(['items', 'location', 'tenant']));

        $this->assertDatabaseHas('pos_order_syncs', [
            'order_id' => $order->id,
            'sync_status' => 'pending',
            'idempotency_key' => 'tenant:'.$this->tenant->id.':order:'.$order->id,
        ]);

        Queue::assertPushed(SyncOrderToPosJob::class);
    }

    public function test_sync_marks_order_synced_in_stub_mode(): void
    {
        TenantContext::set($this->tenant);

        $integration = PosIntegration::query()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'is_enabled' => true,
            'connection_status' => 'connected',
            'credentials' => ['stub_mode' => true],
        ]);

        PosMapping::query()->create([
            'tenant_id' => $this->tenant->id,
            'pos_integration_id' => $integration->id,
            'entity_type' => 'product',
            'local_id' => $this->product->id,
            'external_id' => 'ART-00123',
        ]);

        $order = Order::query()->create([
            'order_number' => 'BO-TEST-002',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 9.5,
            'total' => 9.5,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Margherita',
            'unit_price' => 9.5,
            'quantity' => 1,
            'line_total' => 9.5,
        ]);

        $sync = app(PosOrderSyncService::class)->createOrGetSync($order, $integration);
        $result = app(PosOrderSyncService::class)->processSync($sync);

        $this->assertSame('synced', $result->sync_status);
        $this->assertNotNull($result->external_order_id);
        $this->assertStringStartsWith('POS-', $result->external_order_id);
    }

    public function test_sync_fails_without_product_mapping(): void
    {
        TenantContext::set($this->tenant);

        $integration = PosIntegration::query()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'is_enabled' => true,
            'connection_status' => 'connected',
            'credentials' => ['stub_mode' => true],
        ]);

        $order = Order::query()->create([
            'order_number' => 'BO-TEST-003',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 9.5,
            'total' => 9.5,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Margherita',
            'unit_price' => 9.5,
            'quantity' => 1,
            'line_total' => 9.5,
        ]);

        $sync = app(PosOrderSyncService::class)->createOrGetSync($order, $integration);
        $result = app(PosOrderSyncService::class)->processSync($sync);

        $this->assertSame('failed', $result->sync_status);
        $this->assertStringContainsString('Product ID', (string) $result->last_error);
    }

    public function test_manual_retry_is_idempotent(): void
    {
        $token = $this->admin->issueStaffToken();
        TenantContext::set($this->tenant);

        $integration = PosIntegration::query()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'is_enabled' => true,
            'connection_status' => 'connected',
            'credentials' => ['stub_mode' => true],
        ]);

        PosMapping::query()->create([
            'tenant_id' => $this->tenant->id,
            'pos_integration_id' => $integration->id,
            'entity_type' => 'product',
            'local_id' => $this->product->id,
            'external_id' => 'ART-00123',
        ]);

        $order = Order::query()->create([
            'order_number' => 'BO-TEST-004',
            'location_id' => $this->location->id,
            'status' => 'received',
            'kitchen_status' => 'received',
            'customer_session' => (string) Str::uuid(),
            'subtotal' => 9.5,
            'total' => 9.5,
            'payment_method' => 'pay_at_location',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'station' => 'kitchen',
            'product_name' => 'Margherita',
            'unit_price' => 9.5,
            'quantity' => 1,
            'line_total' => 9.5,
        ]);

        $sync = app(PosOrderSyncService::class)->createOrGetSync($order, $integration);
        app(PosOrderSyncService::class)->processSync($sync);

        $this->withToken($token)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson('/api/admin/pos-syncs/'.$sync->id.'/retry')
            ->assertStatus(422);

        $this->assertSame(1, PosOrderSync::query()->where('order_id', $order->id)->count());
    }

    public function test_pos_mappings_include_resolved_local_labels(): void
    {
        TenantContext::set($this->tenant);
        $token = $this->admin->issueStaffToken();

        $integration = PosIntegration::query()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'epsilon_pylon',
            'is_enabled' => true,
            'credentials' => ['stub_mode' => true],
        ]);

        PosMapping::query()->create([
            'tenant_id' => $this->tenant->id,
            'pos_integration_id' => $integration->id,
            'entity_type' => 'product',
            'local_id' => $this->product->id,
            'external_id' => 'ART-00123',
        ]);

        $this->withToken($token)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withHeader('X-Locale', 'en')
            ->getJson('/api/admin/pos-mappings')
            ->assertOk()
            ->assertJsonPath('data.0.local_label', 'Margherita (#'.$this->product->id.')')
            ->assertJsonPath('data.0.local_name.en', 'Margherita');

        $this->withToken($token)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withHeader('X-Locale', 'en')
            ->getJson('/api/admin/pos-mappings/entities?entity_type=product')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Margherita (#'.$this->product->id.')')
            ->assertJsonPath('data.0.name.en', 'Margherita');
    }
}
