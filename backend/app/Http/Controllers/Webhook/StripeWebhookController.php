<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Monitoring\WebhookMonitor;
use App\Services\StripeOrderPaymentService;
use App\Services\StripeSubscriptionService;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billing,
        protected StripeSubscriptionService $stripeSubscriptions,
        protected StripeOrderPaymentService $orderPayments,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('billing.stripe.webhook_secret');

        if (! $secret) {
            return response('Webhook secret not configured', 503);
        }

        try {
            $event = Webhook::constructEvent($payload, (string) $signature, $secret);
        } catch (UnexpectedValueException) {
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        }

        $this->handleEvent($event);

        return response('OK', 200);
    }

    protected function handleEvent(Event $event): void
    {
        try {
            match ($event->type) {
                'checkout.session.completed' => $this->stripeSubscriptions->handleCheckoutSessionCompleted($event->data->object),
                'customer.subscription.created',
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
                'invoice.paid' => $this->handleInvoicePaid($event),
                'payment_intent.succeeded' => $this->orderPayments->handlePaymentIntentSucceeded($event->data->object),
                'payment_intent.payment_failed' => $this->orderPayments->handlePaymentIntentFailed($event->data->object),
                'charge.refunded' => $this->orderPayments->handleChargeRefunded($event->data->object),
                default => null,
            };
        } catch (\Throwable $e) {
            WebhookMonitor::recordFailure('stripe', $event->type, $e->getMessage());
            throw $e;
        }
    }

    protected function handleInvoicePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;
        $subscription = $this->resolveSubscriptionFromInvoice($invoice);

        if ($subscription) {
            $this->billing->handlePaymentFailed($subscription);
        }
    }

    protected function handleInvoicePaid(Event $event): void
    {
        $invoice = $event->data->object;

        if (($invoice->billing_reason ?? null) === 'subscription_create') {
            return;
        }

        $subscription = $this->resolveSubscriptionFromInvoice($invoice);

        if ($subscription) {
            $this->billing->handlePaymentRecovered($subscription);
        }
    }

    protected function handleSubscriptionUpdated(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $this->stripeSubscriptions->syncFromStripeSubscription($stripeSubscription);
    }

    protected function handleSubscriptionDeleted(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $subscription = $this->billing->findByStripeSubscriptionId($stripeSubscription->id);

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => Subscription::STATUS_EXPIRED,
            'ended_at' => now(),
        ]);

        $subscription->tenant()->update(['is_active' => false]);
    }

    protected function resolveSubscriptionFromInvoice(object $invoice): ?Subscription
    {
        $stripeSubscriptionId = $invoice->subscription ?? null;

        if ($stripeSubscriptionId) {
            return $this->billing->findByStripeSubscriptionId($stripeSubscriptionId);
        }

        $customerId = is_string($invoice->customer ?? null)
            ? $invoice->customer
            : ($invoice->customer->id ?? null);

        if ($customerId) {
            return $this->billing->findByStripeCustomerId($customerId);
        }

        return null;
    }
}
