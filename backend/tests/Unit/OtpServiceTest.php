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
        $code = $otp->generateAndStore(OtpService::PURPOSE_TEST, 'demo@beachorder.test');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($otp->verify(OtpService::PURPOSE_TEST, 'demo@beachorder.test', $code));
        // Consumed — second verify fails
        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'demo@beachorder.test', $code));
    }

    public function test_wrong_code_increments_attempts(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->generateAndStore(OtpService::PURPOSE_TEST, 'demo2@beachorder.test');

        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'demo2@beachorder.test', '000000'));
        $this->assertTrue($otp->verify(OtpService::PURPOSE_TEST, 'demo2@beachorder.test', $code));
    }

    public function test_send_test_dispatches_notification(): void
    {
        Notification::fake();

        $otp = app(OtpService::class);
        $code = $otp->sendTest('test@beachorder.test', OtpService::PURPOSE_PASSWORD_RESET);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        Notification::assertSentOnDemand(\App\Notifications\OtpCodeNotification::class);
    }

    public function test_max_attempts_invalidates_code(): void
    {
        config(['otp.max_attempts' => 2]);
        Cache::flush();

        $otp = app(OtpService::class);
        $code = $otp->generateAndStore(OtpService::PURPOSE_TEST, 'lock@beachorder.test');

        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'lock@beachorder.test', '111111'));
        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'lock@beachorder.test', '222222'));
        $this->assertFalse($otp->verify(OtpService::PURPOSE_TEST, 'lock@beachorder.test', $code));
    }
}
