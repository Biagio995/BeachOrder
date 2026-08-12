<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionReactivatedNotification;
use App\Notifications\SubscriptionSuspendedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionBillingService
{
    /**
     * Mark subscription past_due after a failed payment webhook.
     * Tenant stays active during the grace period; Stripe handles retries.
     */
    public function handlePaymentFailed(Subscription $subscription): void
    {
        if ($subscription->status === Subscription::STATUS_SUSPENDED) {
            return;
        }

        $graceDays = max(0, (int) config('billing.grace_period_days', 7));
        $now = now();

        $subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'past_due_at' => $subscription->past_due_at ?? $now,
            'grace_period_ends_at' => $subscription->grace_period_ends_at ?? $now->copy()->addDays($graceDays),
        ]);

        AuditLogger::log('subscription.payment_failed', $subscription, null, [
            'status' => Subscription::STATUS_PAST_DUE,
            'grace_period_ends_at' => $subscription->grace_period_ends_at?->toIso8601String(),
        ]);

        $subscription->fresh()->syncTenantActivation();
        $this->notifyTenantAdmins($subscription->fresh(), new PaymentFailedNotification($subscription->fresh()));
    }

    /**
     * Suspend tenants whose grace period has expired without a successful payment.
     */
    public function suspendExpiredGracePeriods(): int
    {
        $suspended = 0;

        Subscription::query()
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->with('tenant')
            ->each(function (Subscription $subscription) use (&$suspended) {
                $this->suspendForBilling($subscription);
                $suspended++;
            });

        return $suspended;
    }

    /**
     * Suspend tenant access after tolerance period. No data is deleted.
     */
    public function suspendForBilling(Subscription $subscription): void
    {
        if ($subscription->status === Subscription::STATUS_SUSPENDED) {
            return;
        }

        $old = $subscription->only(['status', 'suspended_at', 'suspension_reason']);

        $subscription->update([
            'status' => Subscription::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => Subscription::SUSPENSION_REASON_BILLING,
        ]);

        AuditLogger::log('subscription.suspended', $subscription, $old, [
            'status' => Subscription::STATUS_SUSPENDED,
            'suspension_reason' => Subscription::SUSPENSION_REASON_BILLING,
        ]);

        $subscription->fresh()->syncTenantActivation();
        $this->notifyTenantAdmins($subscription->fresh(), new SubscriptionSuspendedNotification($subscription->fresh()));
    }

    /**
     * Reactivate subscription and tenant when payment is recovered.
     */
    public function handlePaymentRecovered(Subscription $subscription): void
    {
        if (! $subscription->inRecoverableState()) {
            return;
        }

        $old = $subscription->only(['status', 'past_due_at', 'grace_period_ends_at', 'suspended_at', 'suspension_reason']);

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'past_due_at' => null,
            'grace_period_ends_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        AuditLogger::log('subscription.reactivated', $subscription, $old, [
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->fresh()->syncTenantActivation();
        $this->notifyTenantAdmins($subscription->fresh(), new SubscriptionReactivatedNotification($subscription->fresh()));
    }

    /**
     * Sync subscription fields from a Stripe subscription object.
     *
     * @param  object  $stripeSubscription
     */
    public function syncFromStripeSubscription(Subscription $subscription, object $stripeSubscription): void
    {
        $updates = [
            'stripe_subscription_id' => $stripeSubscription->id ?? $subscription->stripe_subscription_id,
            'stripe_customer_id' => is_string($stripeSubscription->customer ?? null)
                ? $stripeSubscription->customer
                : ($stripeSubscription->customer->id ?? $subscription->stripe_customer_id),
        ];

        if (isset($stripeSubscription->current_period_start)) {
            $updates['current_period_start'] = Carbon::createFromTimestamp($stripeSubscription->current_period_start);
        }

        if (isset($stripeSubscription->current_period_end)) {
            $updates['current_period_end'] = Carbon::createFromTimestamp($stripeSubscription->current_period_end);
        }

        if (isset($stripeSubscription->start_date)) {
            $updates['started_at'] = Carbon::createFromTimestamp($stripeSubscription->start_date);
        }

        if (isset($stripeSubscription->canceled_at)) {
            $updates['canceled_at'] = Carbon::createFromTimestamp($stripeSubscription->canceled_at);
        }

        if (isset($stripeSubscription->ended_at)) {
            $updates['ended_at'] = Carbon::createFromTimestamp($stripeSubscription->ended_at);
        }

        $stripeStatus = $stripeSubscription->status ?? null;

        if ($stripeStatus === 'active' || $stripeStatus === 'trialing') {
            if ($subscription->inRecoverableState()) {
                $this->handlePaymentRecovered($subscription);

                return;
            }

            $updates['status'] = Subscription::STATUS_ACTIVE;
        } elseif ($stripeStatus === 'past_due') {
            $subscription->update($updates);
            $this->handlePaymentFailed($subscription->fresh());

            return;
        } elseif ($stripeStatus === 'canceled') {
            $updates['status'] = Subscription::STATUS_CANCELED;
        } elseif ($stripeStatus === 'unpaid') {
            $subscription->update($updates);
            if ($subscription->gracePeriodExpired()) {
                $this->suspendForBilling($subscription->fresh());
            } else {
                $this->handlePaymentFailed($subscription->fresh());
            }

            return;
        } elseif ($stripeStatus === 'incomplete_expired') {
            $updates['status'] = Subscription::STATUS_EXPIRED;
        } elseif ($stripeStatus === 'incomplete') {
            $updates['status'] = Subscription::STATUS_INACTIVE;
        }

        $subscription->update($updates);
        $subscription->fresh()->syncTenantActivation();
    }

    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription
    {
        return Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();
    }

    public function findByStripeCustomerId(string $stripeCustomerId): ?Subscription
    {
        return Subscription::query()
            ->where('stripe_customer_id', $stripeCustomerId)
            ->first();
    }

    public function upsertForTenant(Tenant $tenant, array $attributes): Subscription
    {
        return Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $attributes,
        );
    }

    protected function notifyTenantAdmins(Subscription $subscription, Notification $notification): void
    {
        $subscription->loadMissing('tenant');

        User::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->each(fn (User $admin) => $admin->notify($notification));
    }
}
