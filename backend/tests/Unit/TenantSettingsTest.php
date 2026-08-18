<?php

namespace Tests\Unit;

use App\Models\Tenant;
use PHPUnit\Framework\TestCase;

class TenantSettingsTest extends TestCase
{
    public function test_default_settings_include_fiscal_pos_and_printing(): void
    {
        $settings = Tenant::defaultSettings([
            'country' => 'GR',
            'fiscal' => ['enabled' => true, 'provider' => 'mydata'],
        ]);

        $this->assertSame('GR', $settings['country']);
        $this->assertTrue($settings['fiscal']['enabled']);
        $this->assertSame('mydata', $settings['fiscal']['provider']);
        $this->assertSame('local_bridge', $settings['fiscal']['mode']);
        $this->assertFalse($settings['pos']['enabled']);
        $this->assertFalse($settings['printing']['enabled']);
        $this->assertSame(9100, $settings['printing']['stations']['kitchen']['port']);
        $this->assertFalse($settings['online_payments_enabled']);
        $this->assertNull($settings['nexi']['alias']);
    }

    public function test_merge_settings_preserves_sibling_keys(): void
    {
        $tenant = new Tenant([
            'settings' => Tenant::defaultSettings([
                'loyalty_enabled' => true,
                'fiscal' => ['enabled' => true, 'device_id' => 'RT-1'],
            ]),
        ]);

        $merged = $tenant->mergeSettings([
            'online_payments_enabled' => false,
            'fiscal' => ['provider' => 'epson_epos'],
            'printing' => ['enabled' => true],
        ]);

        $this->assertTrue($merged['loyalty_enabled']);
        $this->assertFalse($merged['online_payments_enabled']);
        $this->assertTrue($merged['fiscal']['enabled']);
        $this->assertSame('RT-1', $merged['fiscal']['device_id']);
        $this->assertSame('epson_epos', $merged['fiscal']['provider']);
        $this->assertTrue($merged['printing']['enabled']);
        $this->assertSame('escpos_tcp', $merged['printing']['driver']);
    }
}
