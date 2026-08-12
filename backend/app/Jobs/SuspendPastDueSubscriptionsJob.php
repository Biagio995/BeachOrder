<?php

namespace App\Jobs;

use App\Services\SubscriptionBillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SuspendPastDueSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionBillingService $billing): void
    {
        $count = $billing->suspendExpiredGracePeriods();

        if ($count > 0) {
            Log::info('Suspended subscriptions after grace period expired', ['count' => $count]);
        }
    }
}
