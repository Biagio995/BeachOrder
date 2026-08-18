<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'name' => 'Pay Beach',
            'slug' => 'pay-beach',
            'currency' => 'EUR',
            'settings' => Tenant::defaultSettings(),
            'is_active' => true,
        ]);

        TenantContext::set($this->tenant);

        Location::query()->create([
            'name' => 'Tavolo 7',
            'slug' => 'table-7',
            'type' => 'table',
            'code' => 'table7',
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

        TenantContext::clear();

        $this->activateTenantSubscription($this->tenant);
    }

    /**
     * @return array{customer_session: string, access_token: string}
     */
    private function claimAccess(): array
    {
        $session = (string) Str::uuid();

        $claim = $this->postJson('/api/t/pay-beach/locations/code/table7/claim', [
            'customer_session' => $session,
        ])->assertOk();

        return [
            'customer_session' => $session,
            'access_token' => $claim->json('access_token'),
        ];
    }

    public function test_customer_orders_are_pay_at_location(): void
    {
        $access = $this->claimAccess();

        $this->postJson('/api/t/pay-beach/orders', [
            'location_code' => 'table7',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('payment_method', 'pay_at_location')
            ->assertJsonPath('payment_status', 'unpaid');
    }

    public function test_customer_cannot_pay_online(): void
    {
        $access = $this->claimAccess();

        $this->postJson('/api/t/pay-beach/orders', [
            'location_code' => 'table7',
            'access_token' => $access['access_token'],
            'customer_session' => $access['customer_session'],
            'payment_method' => 'card_online',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_menu_does_not_advertise_online_card(): void
    {
        $this->getJson('/api/t/pay-beach/status')
            ->assertOk()
            ->assertJsonPath('tenant.settings.online_payments_enabled', false)
            ->assertJsonPath('tenant.settings.card_online_available', false);
    }
}
