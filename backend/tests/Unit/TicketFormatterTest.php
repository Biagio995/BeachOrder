<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Services\Printing\TicketFormatter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketFormatterTest extends TestCase
{
    public function test_formats_ticket_with_all_fields(): void
    {
        $tenant = new Tenant([
            'timezone' => 'Europe/Rome',
            'default_locale' => 'en',
        ]);

        $order = new Order([
            'order_number' => 'BO-260810-AB3XY',
            'notes' => 'no ice',
            'created_at' => '2026-08-10 10:58:00',
        ]);
        $order->setRelation('tenant', $tenant);
        $order->setRelation('location', new Location([
            'name' => 'Umbrella 12',
            'type' => 'umbrella',
            'zone' => 'A',
        ]));

        $item = new OrderItem([
            'product_name' => 'Spritz',
            'quantity' => 2,
            'variants' => [['group_name' => 'Ice', 'option_name' => 'Normal']],
            'addons' => [['name' => 'Lemon', 'quantity' => 1]],
            'notes' => 'extra spicy',
        ]);

        $text = (new TicketFormatter)->formatText($order, 'bar', collect([$item]), 'en');

        $this->assertStringContainsString('BO-260810-AB3XY', $text);
        $this->assertStringContainsString('Umbrella 12', $text);
        $this->assertStringContainsString('Time:', $text);
        $this->assertStringContainsString('2x Spritz', $text);
        $this->assertStringContainsString('Ice', $text);
        $this->assertStringContainsString('Lemon', $text);
        $this->assertStringContainsString('extra spicy', $text);
        $this->assertStringContainsString('no ice', $text);
    }

    public function test_reprint_marker_in_ticket(): void
    {
        $order = new Order([
            'order_number' => 'BO-TEST',
            'created_at' => now(),
        ]);
        $order->setRelation('tenant', new Tenant(['timezone' => 'UTC']));
        $order->setRelation('location', new Location(['name' => 'T1', 'type' => 'table']));

        $item = new OrderItem(['product_name' => 'Coffee', 'quantity' => 1]);

        $text = (new TicketFormatter)->formatText($order, 'kitchen', collect([$item]), 'en', reprint: true);

        $this->assertStringContainsString('REPRINT', $text);
    }

    public function test_translates_labels_dynamically_for_other_locales(): void
    {
        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => ['translatedText' => 'Ora'],
            ]),
        ]);

        $order = new Order([
            'order_number' => 'BO-IT',
            'created_at' => '2026-08-10 10:58:00',
        ]);
        $order->setRelation('tenant', new Tenant(['timezone' => 'Europe/Rome']));
        $order->setRelation('location', new Location(['name' => '12', 'type' => 'table']));

        $text = (new TicketFormatter)->formatText(
            $order,
            'bar',
            collect([new OrderItem(['product_name' => 'Water', 'quantity' => 1])]),
            'it',
        );

        $this->assertStringContainsString('Ora:', $text);
        Http::assertSentCount(count((new \ReflectionClass(TicketFormatter::class))->getConstant('SOURCE_LABELS')));
    }
}
