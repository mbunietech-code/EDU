<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Models\Order;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateSubscription
{
    public function execute(Order $order, Account $account, Carbon $startDate, Carbon $endDate): Subscription
    {
        return DB::transaction(function () use ($order, $account, $startDate, $endDate) {
            $subscription = Subscription::create([
                'user_id' => $order->user_id,
                'product_id' => $order->product_id,
                'plan_id' => $order->plan_id,
                'account_id' => $account->id,
                'order_id' => $order->id,
                'start_date' => $startDate,
                'expiry_date' => $endDate,
                'status' => 'active',
            ]);

            $account->assign();

            \App\Models\ActivityLog::log(
                'subscription_created',
                'Subscription',
                $subscription->id,
                [
                    'account_id' => $account->id,
                    'start_date' => $startDate->toDateString(),
                    'expiry_date' => $endDate->toDateString(),
                ]
            );

            return $subscription;
        });
    }
}
