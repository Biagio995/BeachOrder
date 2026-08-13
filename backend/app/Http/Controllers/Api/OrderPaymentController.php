<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\StripeOrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function __construct(
        private StripeOrderPaymentService $stripePayments,
    ) {}

    public function show(Request $request, string $tenant, Order $order): JsonResponse
    {
        $this->assertCustomerSession($request, $order);

        if (! in_array($order->payment_method, PaymentService::ONLINE_METHODS, true)) {
            return response()->json(['message' => 'Not an online payment order'], 422);
        }

        if (! $this->stripePayments->isConfigured()) {
            return response()->json(['message' => 'Online payments are not configured'], 503);
        }

        $this->stripePayments->syncPaymentStatus($order);
        $order->refresh();

        if ($order->payment_status === 'paid') {
            return response()->json([
                'order_id' => $order->id,
                'payment_status' => 'paid',
                'amount' => (float) $order->total,
                'currency' => strtoupper($order->tenant?->currency ?? 'EUR'),
                'payment_intent_id' => $order->stripe_payment_intent_id,
                'publishable_key' => (string) config('billing.stripe.key'),
                'client_secret' => null,
            ]);
        }

        $payload = $this->stripePayments->createOrRefreshPaymentIntent($order);

        return response()->json([
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'amount' => (float) $order->total,
            'currency' => strtoupper($order->tenant?->currency ?? 'EUR'),
            ...$payload,
        ]);
    }

    public function receipt(Request $request, string $tenant, Order $order): JsonResponse
    {
        $this->assertCustomerSession($request, $order);

        $receipt = $this->stripePayments->receiptFor($order->load(['items', 'location']));

        if (! $receipt) {
            return response()->json(['message' => 'Payment receipt not available'], 404);
        }

        return response()->json([
            'order' => $order,
            'receipt' => $receipt,
        ]);
    }

    protected function assertCustomerSession(Request $request, Order $order): void
    {
        $session = $request->query('session') ?? $request->input('session');

        abort_unless(
            $session && $order->customer_session && hash_equals($order->customer_session, (string) $session),
            403,
            'Forbidden',
        );
    }
}
