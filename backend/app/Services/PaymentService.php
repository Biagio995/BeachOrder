<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class PaymentService
{
    public const METHODS = ['pay_at_location', 'card_online', 'apple_pay', 'google_pay'];

    public const ONLINE_METHODS = ['card_online', 'apple_pay', 'google_pay'];

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
        return in_array($method, self::ONLINE_METHODS, true);
    }

    public function canStaffSetPaymentStatus(Order $order, string $status): bool
    {
        if ($this->isOnlineMethod($order->payment_method) && $status === 'paid') {
            return false;
        }

        if ($this->isOnlineMethod($order->payment_method) && $status === 'pending') {
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
