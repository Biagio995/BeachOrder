<?php

namespace Tests\Unit;

use App\Services\OtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    public function test_generate_store_and_verify(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->generateAndStore(OtpService::PURPOSE_TEST, 'demo@servio.test');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($otp->verify(OtpService::PURPOSE_TEST, 'demo@servio.test', $code));
        // Consumed — second verify fails
        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'demo@servio.test', $code));
    }

    public function test_wrong_code_increments_attempts(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->generateAndStore(OtpService::PURPOSE_TEST, 'demo2@servio.test');

        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'demo2@servio.test', '000000'));
        $this->assertTrue($otp->verify(OtpService::PURPOSE_TEST, 'demo2@servio.test', $code));
    }

    public function test_send_test_dispatches_notification(): void
    {
        Notification::fake();

        $otp = app(OtpService::class);
        $code = $otp->sendTest('test@servio.test', OtpService::PURPOSE_PASSWORD_RESET);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        Notification::assertSentOnDemand(\App\Notifications\OtpCodeNotification::class);
    }

    public function test_send_test_can_brand_with_tenant(): void
    {
        Notification::fake();

        $tenant = new \App\Models\Tenant([
            'name' => 'Pizzeria Bella',
            'slug' => 'mail-test-pizzeria',
        ]);

        $otp = app(OtpService::class);
        $otp->sendTest('onboard@servio.test', OtpService::PURPOSE_EMAIL_VERIFICATION, $tenant);

        Notification::assertSentOnDemand(
            \App\Notifications\OtpCodeNotification::class,
            function (\App\Notifications\OtpCodeNotification $n) {
                return $n->purpose === OtpService::PURPOSE_EMAIL_VERIFICATION
                    && $n->tenant?->slug === 'mail-test-pizzeria';
            }
        );
    }

    public function test_max_attempts_invalidates_code(): void
    {
        config(['otp.max_attempts' => 2]);
        Cache::flush();

        $otp = app(OtpService::class);
        $code = $otp->generateAndStore(OtpService::PURPOSE_TEST, 'lock@servio.test');

        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'lock@servio.test', '111111'));
        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'lock@servio.test', '222222'));
        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'lock@servio.test', $code));
    }
}
