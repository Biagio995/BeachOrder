<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NexiXPayService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * US-11 — Online card payment acceptance tests (Nexi XPay).
 */
class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    private Product $product;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://api.test',
            'app.frontend_url' => 'http://frontend.test',
        ]);

        $this->tenant = Tenant::query()->create([
            'name' => 'Pay Beach',
            'slug' => 'pay-beach',
            'currency' => 'EUR',
            'settings' => Tenant::defaultSettings([
                'online_payments_enabled' => true,
                'nexi' => [
                    'alias' => 'test_alias',
                    'secret_key' => 'test_secret_key',
                    'environment' => 'test',
                ],
            ]),
            'is_active' => true,
        ]);

        TenantContext::set($this->tenant);

        $this->location = Location::query()->create([
            'name' => 'Ombrellone 7',
            'slug' => 'umb-7',
            'type' => 'umbrella',
            'code' => 'umbrella7',
            'capacity' => 2,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => ['it' => 'Bar'],
            'slug' => 'bar',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::query()->create([
            'category_id' => $category->id,
            'station' => 'bar',
            'name' => ['it' => 'Spritz'],
            'slug' => 'spritz',
            'price' => 8.5,
            'allergens' => [],
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->staff = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff',
            'email' => 'staff@pay.beach',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'staff_position' => User::STAFF_POSITION_BAR,
            'is_active' => true,
        ]);
        $this->staff->markEmailAsVerified();

        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@pay.beach',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $this->admin->markEmailAsVerified();

        TenantContext::clear();

        $this->activateTenantSubscription($this->tenant);
    }

    /**
     * @return array{customer_session: string, access_token: string}
     */
    private function claimAccess(): array
    {
        $session = (string) Str::uuid();

        $claim = $this->postJson('/api/t/pay-beach/locations/code/umbrella7/claim', [
            'customer_session' => $session,
        ])->assertOk();

        return [
            'customer_session' => $session,
            'access_token' => $claim->json('access_token'),
        ];
    }

    public function test_card_online_order_stays_pending(): void
    {
        $access = $this->claimAccess();

        $response = $this->postJson('/api/t/pay-beach/orders', [
            'location_code' => 'umbrella7',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'payment_method' => 'card_online',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('payment_method', 'card_online');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('id'),
            'payment_status' => 'pending',
        ]);
    }

    public function test_card_online_requires_nexi_configuration(): void
    {
        $this->tenant->update([
            'settings' => Tenant::defaultSettings([
                'online_payments_enabled' => true,
                'nexi' => ['alias' => null, 'secret_key' => null],
            ]),
        ]);

        $access = $this->claimAccess();

        $this->postJson('/api/t/pay-beach/orders', [
            'location_code' => 'umbrella7',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'payment_method' => 'card_online',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOrder(array $attributes = []): Order
    {
        TenantContext::set($this->tenant);

        return Order::query()->create(array_merge([
            'location_id' => $this->location->id,
            'status' => 'received',
            'bar_status' => 'received',
            'subtotal' => 8.5,
            'total' => 8.5,
        ], $attributes));
    }

    public function test_staff_cannot_mark_card_order_as_paid(): void
    {
        Sanctum::actingAs($this->admin);

        $order = $this->createOrder([
            'order_number' => 'BO-TEST-001',
            'customer_session' => (string) Str::uuid(),
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
            'payment_reference' => 'BO-PAY-TEST1234',
        ]);

        $this->withHeader('X-Tenant', 'pay-beach')
            ->patchJson("/api/orders/{$order->id}/payment", [
                'payment_status' => 'paid',
            ])->assertStatus(422);
    }

    public function test_customer_receives_nexi_redirect_session(): void
    {
        $session = (string) Str::uuid();

        $order = $this->createOrder([
            'order_number' => 'BO-TEST-002',
            'customer_session' => $session,
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
            'payment_reference' => 'BO-PAY-TEST1234',
        ]);

        $this->getJson("/api/t/pay-beach/orders/{$order->id}/payment?session={$session}")
            ->assertOk()
            ->assertJsonPath('type', 'redirect')
            ->assertJsonPath('provider', 'nexi')
            ->assertJsonPath('fields.alias', 'test_alias')
            ->assertJsonPath('gateway_url', 'https://int-ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet');
    }

    public function test_nexi_webhook_marks_order_paid(): void
    {
        $order = $this->createOrder([
            'order_number' => 'BO-TEST-003',
            'customer_session' => (string) Str::uuid(),
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
            'payment_reference' => 'BO-PAY-WEBHOOK1',
        ]);

        $nexi = app(NexiXPayService::class);
        $codTrans = $nexi->codTransFor($order);
        $importo = '850';
        $divisa = 'EUR';
        $data = '20260817';
        $orario = '120000';
        $codAut = 'AUTH01';
        $esito = 'OK';
        $mac = sha1("codTrans={$codTrans}esito={$esito}importo={$importo}divisa={$divisa}data={$data}orario={$orario}codAut={$codAut}test_secret_key");

        $this->post('/api/webhooks/nexi/pay-beach', [
            'codTrans' => $codTrans,
            'esito' => $esito,
            'importo' => $importo,
            'divisa' => $divisa,
            'data' => $data,
            'orario' => $orario,
            'codAut' => $codAut,
            'mac' => $mac,
        ])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('AUTH01', $order->payment_authorization_code);
        $this->assertNotNull($order->paid_at);
    }

    public function test_unpaid_online_orders_hidden_from_kitchen_board(): void
    {
        $this->createOrder([
            'order_number' => 'BO-PENDING',
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
        ]);

        $this->createOrder([
            'order_number' => 'BO-PAID',
            'payment_method' => 'card_online',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Tenant', 'pay-beach')
            ->getJson('/api/orders?station=bar');

        $response->assertOk();
        $numbers = collect($response->json('data'))->pluck('order_number')->all();

        $this->assertNotContains('BO-PENDING', $numbers);
        $this->assertContains('BO-PAID', $numbers);
    }
}
