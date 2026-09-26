<?php

namespace Tests\Unit;

use App\Support\OrderNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderNumberTest extends TestCase
{
    public function test_default_prefix_is_ord(): void
    {
        config(['orders.number_prefix' => 'ORD']);

        $this->assertSame('ORD', OrderNumber::prefix());
        $this->assertMatchesRegularExpression(
            '/^ORD-\d{6}-[A-Z0-9]{5}$/',
            OrderNumber::generate()
        );
    }

    public function test_configured_prefix_is_used(): void
    {
        config(['orders.number_prefix' => 'DP']);

        $this->assertSame('DP', OrderNumber::prefix());
        $this->assertMatchesRegularExpression(
            '/^DP-\d{6}-[A-Z0-9]{5}$/',
            OrderNumber::generate()
        );
    }

    public function test_prefix_is_trimmed_and_uppercased(): void
    {
        config(['orders.number_prefix' => '  dp  ']);

        $this->assertSame('DP', OrderNumber::prefix());
    }

    #[DataProvider('invalidPrefixes')]
    public function test_invalid_or_empty_prefix_falls_back_to_ord(string $invalid): void
    {
        config(['orders.number_prefix' => $invalid]);

        $this->assertSame('ORD', OrderNumber::prefix());
        $this->assertStringStartsWith('ORD-', OrderNumber::generate());
    }

    public static function invalidPrefixes(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'too_long' => ['TOOLONG'],
            'lowercase_symbols' => ['bo!'],
            'hyphen' => ['BO-'],
            'underscore' => ['ORD_1'],
        ];
    }
}
