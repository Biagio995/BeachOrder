<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class PaymentService
{
    public const METHODS = ['pay_at_location', 'card_online'];

    /** @var list<string> */
    public const ONLINE_METHODS = ['card_online'];

    /** @var list<string> Legacy Stripe checkout methods kept for existing orders. */
    public const STRIPE_METHODS = ['card_online', 'apple_pay', 'google_pay'];

    public function markPending(Order $order, string $method): Order
    {
        $order->payment_method = $method;
        $order->payment_status = in_array($method, self::ONLINE_METHODS, true)
            ? 'pending'
            : 'unpaid';
        $order->payment_reference = 'BO-PAY-'.Str::upper(Str::random(8));
        $order->save();

        return $order;
    }

    public function isOnlineMethod(?string $method): bool
    {
        return in_array($method, self::ONLINE_METHODS, true)
            || in_array($method, self::STRIPE_METHODS, true);
    }

    public function isStripeMethod(?string $method): bool
    {
        return in_array($method, self::STRIPE_METHODS, true);
    }

    public function canStaffSetPaymentStatus(Order $order, string $status): bool
    {
        if ($this->isOnlineMethod($order->payment_method) && in_array($status, ['paid', 'pending'], true)) {
            return false;
        }

        return true;
    }

    public function markPaidAtLocation(Order $order): Order
    {
        $order->payment_status = 'paid';
        $order->save();

        return $order;
    }
}
