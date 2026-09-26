<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DemoMode;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class StripeSubscriptionService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly SubscriptionBillingService $billing,
    ) {}

    public function isConfigured(): bool
    {
        if (! filled(config('billing.stripe.secret')) || ! filled(config('billing.stripe.price_annual'))) {
            return false;
        }

        // Demo safety: live Stripe keys are treated as "not configured".
        if (DemoMode::enabled() && ! DemoMode::stripeKeysAreTestOnly()) {
            return false;
        }

        return true;
    }

    public function client(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient((string) config('billing.stripe.secret'));
        }

        return $this->client;
    }

    /**
     * @return array{url: string}
     *
     * @throws ApiErrorException
     */
    public function createCheckoutSession(Tenant $tenant, User $user, string $returnContext = 'settings'): array
    {
        $this->ensureConfigured();

        $subscription = $this->ensureSubscriptionRecord($tenant);
        $customerId = $this->ensureStripeCustomer($subscription, $tenant, $user);

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if ($returnContext === 'registration') {
            $successUrl = $frontend.'/verify-email?checkout=success';
            $cancelUrl = $frontend.'/register?checkout=canceled';
        } else {
            $successUrl = $frontend.'/admin/settings/subscription?checkout=success';
            $cancelUrl = $frontend.'/admin/settings/subscription?checkout=canceled';
        }

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $tenant->id,
            'line_items' => [[
                'price' => config('billing.stripe.price_annual'),
                'quantity' => 1,
            ]],
            'automatic_tax' => ['enabled' => true],
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
            'tax_id_collection' => ['enabled' => true],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                ],
            ],
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return ['url' => $session->url];
    }

    /**
     * @return array{url: string}
     *
     * @throws ApiErrorException
     */
    public function createPortalSession(Tenant $tenant): array
    {
        $this->ensureConfigured();

        $subscription = $this->ensureSubscriptionRecord($tenant);

        if (! $subscription->stripe_customer_id) {
            throw new \RuntimeException('No Stripe customer for this tenant.');
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $subscription->stripe_customer_id,
            'return_url' => $frontend.'/admin/settings/subscription',
        ]);

        return ['url' => $session->url];
    }

    public function handleCheckoutSessionCompleted(CheckoutSession $session): void
    {
        $tenantId = (int) ($session->client_reference_id ?: ($session->metadata['tenant_id'] ?? 0));
        if (! $tenantId) {
            return;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return;
        }

        $subscription = $this->ensureSubscriptionRecord($tenant);

        if ($session->customer) {
            $subscription->stripe_customer_id = is_string($session->customer)
                ? $session->customer
                : $session->customer->id;
        }

        if ($session->subscription) {
            $stripeSubscriptionId = is_string($session->subscription)
                ? $session->subscription
                : $session->subscription->id;

            $stripeSubscription = $this->client()->subscriptions->retrieve($stripeSubscriptionId);
            $subscription->stripe_subscription_id = $stripeSubscription->id;
            $this->syncFromStripeSubscription($stripeSubscription);

            return;
        }

        $subscription->save();
    }

    public function syncFromStripeSubscription(StripeSubscription $stripeSubscription): ?Subscription
    {
        $tenantId = $stripeSubscription->metadata['tenant_id'] ?? null;

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->when($tenantId, fn ($q) => $q->orWhere('tenant_id', (int) $tenantId))
            ->first();

        if (! $subscription && $tenantId) {
            $subscription = $this->ensureSubscriptionRecord(Tenant::query()->findOrFail((int) $tenantId));
        }

        if (! $subscription) {
            return null;
        }

        $subscription->fill([
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => is_string($stripeSubscription->customer)
                ? $stripeSubscription->customer
                : ($stripeSubscription->customer->id ?? $subscription->stripe_customer_id),
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => (int) config('billing.annual_price_cents', 29900),
            'currency' => strtoupper((string) ($stripeSubscription->currency ?: 'EUR')),
            'started_at' => $this->timestampToCarbon($stripeSubscription->start_date),
            'current_period_start' => $this->timestampToCarbon($stripeSubscription->current_period_start),
            'current_period_end' => $this->timestampToCarbon($stripeSubscription->current_period_end),
            'canceled_at' => $this->timestampToCarbon($stripeSubscription->canceled_at),
            'ended_at' => $this->timestampToCarbon($stripeSubscription->ended_at),
        ])->save();

        $this->billing->syncFromStripeSubscription($subscription->fresh(), $stripeSubscription);

        $subscription->refresh()->syncTenantActivation();

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayloadForTenant(Tenant $tenant): array
    {
        if ($tenant->isDemo()) {
            return array_merge([
                'status' => Subscription::STATUS_ACTIVE,
                'plan' => Subscription::PLAN_ANNUAL,
                'price_cents' => (int) config('billing.annual_price_cents', 29900),
                'currency' => $tenant->currency ?: 'EUR',
                'started_at' => null,
                'current_period_start' => null,
                'current_period_end' => null,
                'next_renewal_at' => null,
                'expires_at' => null,
                'canceled_at' => null,
                'ended_at' => null,
                'grace_period_ends_at' => null,
                'grants_access' => true,
            ], [
                'pricing' => $this->pricingSummary(),
            ]);
        }

        $subscription = $tenant->subscription;

        if (! $subscription) {
            return array_merge([
                'status' => Subscription::STATUS_INACTIVE,
                'plan' => Subscription::PLAN_ANNUAL,
                'price_cents' => (int) config('billing.annual_price_cents', 29900),
                'currency' => $tenant->currency ?: 'EUR',
                'started_at' => null,
                'current_period_start' => null,
                'current_period_end' => null,
                'next_renewal_at' => null,
                'expires_at' => null,
                'canceled_at' => null,
                'ended_at' => null,
                'grace_period_ends_at' => null,
                'grants_access' => false,
            ], [
                'pricing' => $this->pricingSummary(),
            ]);
        }

        return array_merge($subscription->toPublicArray(), [
            'pricing' => $this->pricingSummary(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pricingSummary(): array
    {
        return [
            'amount_cents' => (int) config('billing.annual_price_cents', 29900),
            'currency' => 'EUR',
            'interval' => 'year',
            'vat_note' => config('billing.annual_vat_note', '+ VAT'),
        ];
    }

    private function ensureSubscriptionRecord(Tenant $tenant): Subscription
    {
        return $this->billing->upsertForTenant($tenant, [
            'status' => Subscription::STATUS_INACTIVE,
            'plan' => Subscription::PLAN_ANNUAL,
            'price_cents' => (int) config('billing.annual_price_cents', 29900),
            'currency' => $tenant->currency ?: 'EUR',
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    private function ensureStripeCustomer(Subscription $subscription, Tenant $tenant, User $user): string
    {
        if ($subscription->stripe_customer_id) {
            return $subscription->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $user->email,
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ]);

        $subscription->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    private function timestampToCarbon(?int $timestamp): ?\Illuminate\Support\Carbon
    {
        if (! $timestamp) {
            return null;
        }

        return \Illuminate\Support\Carbon::createFromTimestampUTC($timestamp);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Stripe subscription billing is not configured.');
        }
    }
}
