<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Starts a customer renewal: a new pending order for the same product (same
 * plan unless the customer picks another one) that points at the order it
 * renews. Paying it extends the same subscription / VPN. Shared by the web
 * order page and the app API.
 */
class OrderRenewalService
{
    public function __construct(private NotificationService $notifications) {}

    /** @throws \DomainException with a message for the customer */
    public function start(Order $order, ?int $planId = null): Order
    {
        if (! $order->canBeRenewed()) {
            throw new \DomainException('This order cannot be renewed yet. You can renew from '.Order::RENEW_WINDOW_DAYS.' days before it ends.');
        }

        $plans = fn () => $order->product->plans()->active();
        $plan = $planId
            ? $plans()->whereKey($planId)->first()
            : ($plans()->whereKey($order->plan_id)->first() ?? $plans()->orderBy('sort_order')->first());

        if (! $plan) {
            throw new \DomainException('That plan is not available. Please pick another one.');
        }

        $renewal = Order::create([
            'user_id' => $order->user_id,
            'order_number' => $this->orderNumber(),
            'product_id' => $order->product_id,
            'plan_id' => $plan->id,
            'renews_order_id' => $order->id,
            'amount' => $plan->price,
            'status' => 'pending',
        ]);

        $this->notifications->notifyOrderCreated($order->user, $renewal);
        ActivityLog::log('order_renewal_created', 'Order', $renewal->id, [
            'order_number' => $renewal->order_number,
            'renews' => $order->order_number,
            'amount' => $renewal->amount,
        ]);

        return $renewal;
    }

    private function orderNumber(): string
    {
        do {
            $number = 'MBT-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
