<?php

namespace App\Services;

use App\Events\OrderUpdated;
use App\Models\Order;
use App\Services\Monitoring\WebhookMonitor;
use App\Services\Pos\PosOrderSyncService;
use App\Services\Printing\PrintService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeOrderPaymentService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('billing.stripe.secret')) && filled(config('billing.stripe.key'));
    }

    public function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient((string) config('billing.stripe.secret'));
        }

        return $this->client;
    }

    /**
     * Pull the latest PaymentIntent state from Stripe and mark the order paid when succeeded.
     */
    public function syncPaymentStatus(Order $order): bool
    {
        if ($order->payment_status === 'paid') {
            return true;
        }

        if (! in_array($order->payment_method, PaymentService::ONLINE_METHODS, true)) {
            return false;
        }

        if (! $this->isConfigured() || ! $order->stripe_payment_intent_id) {
            return false;
        }

        try {
            $intent = $this->client()->paymentIntents->retrieve($order->stripe_payment_intent_id);

            if ($intent->status === 'succeeded') {
                $this->markPaidFromIntent($order, $intent);

                return true;
            }
        } catch (ApiErrorException $e) {
            Log::channel('payments')->warning('payment.sync_failed', [
                'order_id' => $order->id,
                'payment_intent_id' => $order->stripe_payment_intent_id,
                'message' => $e->getMessage(),
            ]);
        }

        return $order->fresh()->payment_status === 'paid';
    }

    /**
     * @return array{client_secret: string, publishable_key: string, payment_intent_id: string}
     *
     * @throws ApiErrorException
     */
    public function createOrRefreshPaymentIntent(Order $order): array
    {
        $this->ensureConfigured();
        $this->assertPayableOnline($order);

        if ($order->stripe_payment_intent_id) {
            $existing = $this->client()->paymentIntents->retrieve($order->stripe_payment_intent_id);

            if (in_array($existing->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                return $this->paymentPayload($existing);
            }

            if ($existing->status === 'succeeded') {
                $this->markPaidFromIntent($order, $existing);

                throw ValidationException::withMessages([
                    'payment' => ['This order is already paid.'],
                ]);
            }
        }

        $tenant = $order->tenant;
        $currency = strtolower($tenant?->currency ?? 'eur');
        $amountCents = (int) round(((float) $order->total) * 100);

        if ($amountCents < 50) {
            throw ValidationException::withMessages([
                'payment' => ['Order total is below the minimum charge amount.'],
            ]);
        }

        $intent = $this->client()->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $currency,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'order_id' => (string) $order->id,
                'tenant_id' => (string) $order->tenant_id,
                'order_number' => (string) $order->order_number,
            ],
        ]);

        $order->stripe_payment_intent_id = $intent->id;
        $order->payment_status = 'pending';
        $order->payment_error = null;
        $order->save();

        return $this->paymentPayload($intent);
    }

    public function handlePaymentIntentSucceeded(object $paymentIntent): void
    {
        $order = $this->resolveOrderFromIntent($paymentIntent);

        if (! $order) {
            Log::channel('payments')->warning('payment.webhook.orphan_intent', [
                'payment_intent_id' => $paymentIntent->id ?? null,
            ]);

            return;
        }

        if ($order->payment_status === 'paid') {
            WebhookMonitor::recordSuccess('stripe', 'payment_intent.succeeded');

            return;
        }

        $this->markPaidFromIntent($order, $paymentIntent);
        WebhookMonitor::recordSuccess('stripe', 'payment_intent.succeeded');
    }

    public function handlePaymentIntentFailed(object $paymentIntent): void
    {
        $order = $this->resolveOrderFromIntent($paymentIntent);

        if (! $order) {
            WebhookMonitor::recordFailure('stripe', 'payment_intent.payment_failed', 'order not found');

            return;
        }

        if ($order->payment_status === 'paid') {
            WebhookMonitor::recordSuccess('stripe', 'payment_intent.payment_failed');

            return;
        }

        $errorMessage = $paymentIntent->last_payment_error->message
            ?? $paymentIntent->last_payment_error->code
            ?? 'Payment failed';

        $order->payment_status = 'failed';
        $order->payment_error = (string) $errorMessage;
        $order->save();

        try {
            event(new OrderUpdated($order->load(['items', 'location'])));
        } catch (\Throwable) {
            //
        }

        WebhookMonitor::recordSuccess('stripe', 'payment_intent.payment_failed');
    }

    public function handleChargeRefunded(object $charge): void
    {
        $paymentIntentId = is_string($charge->payment_intent ?? null)
            ? $charge->payment_intent
            : ($charge->payment_intent->id ?? null);

        if (! $paymentIntentId) {
            return;
        }

        $order = Order::withoutGlobalScopes()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();

        if (! $order || $order->payment_status === 'refunded') {
            WebhookMonitor::recordSuccess('stripe', 'charge.refunded');

            return;
        }

        DB::transaction(function () use ($order) {
            $wasPaid = $order->payment_status === 'paid';
            $order->payment_status = 'refunded';
            $order->save();

            if ($wasPaid && $order->status !== 'cancelled') {
                $order->status = 'cancelled';
                $order->cancelled_at = now();
                $order->save();
                $this->inventory->restoreForOrder($order);
            }
        });

        try {
            event(new OrderUpdated($order->fresh(['items', 'location'])));
        } catch (\Throwable) {
            //
        }

        WebhookMonitor::recordSuccess('stripe', 'charge.refunded');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function receiptFor(Order $order): ?array
    {
        if ($order->payment_status !== 'paid') {
            return null;
        }

        return [
            'reference' => $order->payment_reference,
            'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'amount' => (float) $order->total,
            'currency' => strtoupper($order->tenant?->currency ?? 'EUR'),
            'method' => $order->payment_method,
        ];
    }

    protected function markPaidFromIntent(Order $order, object $paymentIntent): void
    {
        $wasPaid = false;

        DB::transaction(function () use ($order, $paymentIntent, &$wasPaid) {
            $order->refresh();

            if ($order->payment_status === 'paid') {
                return;
            }

            $order->payment_status = 'paid';
            $order->stripe_payment_intent_id = $paymentIntent->id ?? $order->stripe_payment_intent_id;
            $order->paid_at = now();
            $order->payment_error = null;

            if (! $order->payment_reference) {
                $order->payment_reference = 'BO-PAY-'.strtoupper(substr($paymentIntent->id, -8));
            }

            $order->save();
            $wasPaid = true;
        });

        if (! $wasPaid) {
            return;
        }

        $order = $order->fresh(['items', 'location', 'tenant']);

        try {
            event(new OrderUpdated($order));
        } catch (\Throwable) {
            //
        }

        try {
            app(PrintService::class)->dispatchOrderPrint($order);
        } catch (\Throwable) {
            //
        }

        try {
            app(PosOrderSyncService::class)->queueForOrder($order);
        } catch (\Throwable) {
            //
        }
    }

    protected function resolveOrderFromIntent(object $paymentIntent): ?Order
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if ($orderId) {
            $order = Order::withoutGlobalScopes()->find($orderId);
            if ($order) {
                TenantContext::set($order->tenant);

                return $order;
            }
        }

        if ($paymentIntent->id ?? null) {
            $order = Order::withoutGlobalScopes()
                ->where('stripe_payment_intent_id', $paymentIntent->id)
                ->first();

            if ($order) {
                TenantContext::set($order->tenant);

                return $order;
            }
        }

        return null;
    }

    protected function assertPayableOnline(Order $order): void
    {
        if (! in_array($order->payment_method, PaymentService::ONLINE_METHODS, true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['This order does not use online payment.'],
            ]);
        }

        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'payment' => ['This order is already paid.'],
            ]);
        }

        if ($order->payment_status === 'refunded') {
            throw ValidationException::withMessages([
                'payment' => ['This order was refunded and cannot be paid again.'],
            ]);
        }

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'payment' => ['This order was cancelled.'],
            ]);
        }
    }

    /**
     * @return array{client_secret: string, publishable_key: string, payment_intent_id: string}
     */
    protected function paymentPayload(object $intent): array
    {
        return [
            'client_secret' => (string) $intent->client_secret,
            'publishable_key' => (string) config('billing.stripe.key'),
            'payment_intent_id' => (string) $intent->id,
        ];
    }

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => ['Online payments are not configured for this venue.'],
            ]);
        }
    }
}
