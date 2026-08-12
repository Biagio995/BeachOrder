<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeOrderPaymentService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * US-11 — Online payment acceptance tests.
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
            'billing.stripe.key' => 'pk_test_fake',
            'billing.stripe.secret' => 'sk_test_fake',
            'billing.stripe.webhook_secret' => 'whsec_test_secret',
        ]);

        $this->tenant = Tenant::query()->create([
            'name' => 'Pay Beach',
            'slug' => 'pay-beach',
            'currency' => 'EUR',
            'settings' => ['online_payments_enabled' => true],
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

    private function mockStripePaymentCreation(): void
    {
        $this->mock(StripeOrderPaymentService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createOrRefreshPaymentIntent')->andReturnUsing(function (Order $order) {
                $order->stripe_payment_intent_id = 'pi_test_'.Str::random(8);
                $order->payment_status = 'pending';
                $order->payment_error = null;
                $order->save();

                return [
                    'client_secret' => 'pi_test_secret_'.Str::random(8),
                    'publishable_key' => 'pk_test_fake',
                    'payment_intent_id' => $order->stripe_payment_intent_id,
                ];
            });
            $mock->shouldReceive('receiptFor')->andReturnUsing(function (Order $order) {
                if ($order->payment_status !== 'paid') {
                    return null;
                }

                return [
                    'reference' => $order->payment_reference,
                    'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                    'paid_at' => $order->paid_at?->toIso8601String(),
                    'amount' => (float) $order->total,
                    'currency' => 'EUR',
                    'method' => $order->payment_method,
                ];
            });
        });
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

    public function test_online_order_stays_pending_and_returns_payment_intent(): void
    {
        $this->mockStripePaymentCreation();
        $access = $this->claimAccess();

        $response = $this->postJson('/api/t/pay-beach/orders', [
            'location_code' => 'umbrella7',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'payment_method' => 'card_online',
            'confirm_payment' => true,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('payment.client_secret', fn ($v) => filled($v))
            ->assertJsonPath('payment.publishable_key', 'pk_test_fake');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('id'),
            'payment_status' => 'pending',
        ]);
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

    private function stripeSignature(string $payload): string
    {
        $timestamp = time();
        $secret = (string) config('billing.stripe.webhook_secret');
        $signed = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signed}";
    }

    public function test_frontend_cannot_mark_online_order_as_paid(): void
    {
        Sanctum::actingAs($this->admin);

        $order = $this->createOrder([
            'order_number' => 'BO-TEST-001',
            'customer_session' => (string) Str::uuid(),
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
            'stripe_payment_intent_id' => 'pi_test_blocked',
        ]);

        $this->withHeader('X-Tenant', 'pay-beach')
            ->patchJson("/api/orders/{$order->id}/payment", [
                'payment_status' => 'paid',
            ])->assertStatus(422);
    }

    public function test_webhook_marks_order_paid(): void
    {
        $order = $this->createOrder([
            'order_number' => 'BO-TEST-002',
            'customer_session' => (string) Str::uuid(),
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
            'payment_reference' => 'BO-PAY-TEST1234',
            'stripe_payment_intent_id' => 'pi_test_success',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_success',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_success',
                    'object' => 'payment_intent',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'tenant_id' => (string) $this->tenant->id,
                    ],
                ],
            ],
        ]);

        $signature = $this->stripeSignature($payload);

        $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_webhook_marks_order_failed(): void
    {
        $order = $this->createOrder([
            'order_number' => 'BO-TEST-003',
            'customer_session' => (string) Str::uuid(),
            'payment_method' => 'card_online',
            'payment_status' => 'pending',
            'stripe_payment_intent_id' => 'pi_test_failed',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_failed',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_failed',
                    'object' => 'payment_intent',
                    'metadata' => ['order_id' => (string) $order->id],
                    'last_payment_error' => ['message' => 'Your card was declined.'],
                ],
            ],
        ]);

        $signature = $this->stripeSignature($payload);

        $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk();

        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('Your card was declined.', $order->payment_error);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->postJson('/api/webhooks/stripe', ['type' => 'payment_intent.succeeded'], [
            'Stripe-Signature' => 'invalid',
        ])->assertStatus(400);
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

    public function test_customer_can_fetch_payment_receipt_when_paid(): void
    {
        $this->mockStripePaymentCreation();
        $session = (string) Str::uuid();

        $order = $this->createOrder([
            'order_number' => 'BO-TEST-004',
            'customer_session' => $session,
            'payment_method' => 'card_online',
            'payment_status' => 'paid',
            'payment_reference' => 'BO-PAY-RCPT1234',
            'stripe_payment_intent_id' => 'pi_test_receipt',
            'paid_at' => now(),
        ]);

        $this->getJson("/api/t/pay-beach/orders/{$order->id}?session={$session}")
            ->assertOk()
            ->assertJsonPath('payment_receipt.reference', 'BO-PAY-RCPT1234')
            ->assertJsonPath('payment_receipt.stripe_payment_intent_id', 'pi_test_receipt');

        $this->getJson("/api/t/pay-beach/orders/{$order->id}/payment/receipt?session={$session}")
            ->assertOk()
            ->assertJsonPath('receipt.amount', 8.5);
    }

    public function test_customer_can_retry_failed_payment(): void
    {
        $this->mockStripePaymentCreation();
        $session = (string) Str::uuid();

        $order = $this->createOrder([
            'order_number' => 'BO-TEST-005',
            'customer_session' => $session,
            'payment_method' => 'card_online',
            'payment_status' => 'failed',
            'payment_error' => 'Card declined',
            'stripe_payment_intent_id' => 'pi_test_old',
        ]);

        $this->getJson("/api/t/pay-beach/orders/{$order->id}/payment?session={$session}")
            ->assertOk()
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonStructure(['client_secret', 'publishable_key', 'payment_intent_id']);
    }
}
