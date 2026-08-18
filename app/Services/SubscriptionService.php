<?php

namespace App\Services;

use App\Actions\Subscriptions\CreateSubscription;
use App\Actions\Subscriptions\ExtendSubscription;
use App\Actions\Subscriptions\ExpireSubscription;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    protected CreateSubscription $createSubscription;
    protected ExtendSubscription $extendSubscription;
    protected ExpireSubscription $expireSubscription;
    protected AccountAssignmentService $accountAssignmentService;

    public function __construct(
        CreateSubscription $createSubscription,
        ExtendSubscription $extendSubscription,
        ExpireSubscription $expireSubscription,
        AccountAssignmentService $accountAssignmentService
    ) {
        $this->createSubscription = $createSubscription;
        $this->extendSubscription = $extendSubscription;
        $this->expireSubscription = $expireSubscription;
        $this->accountAssignmentService = $accountAssignmentService;
    }

    public function createFromOrder(Order $order): Subscription
    {
        return DB::transaction(function () use ($order) {
            $account = $this->accountAssignmentService->findAvailableAccount($order->product_id);

            if (! $account) {
                throw new \RuntimeException('No available account for product: ' . $order->product->name);
            }

            $startDate = Carbon::now(config('app.timezone'));

            if ($order->plan->isLifetime()) {
                $endDate = Carbon::parse('2099-12-31', config('app.timezone'));
            } else {
                $endDate = $startDate->copy()->addDays($order->plan->duration_days);
            }

            return $this->createSubscription->execute($order, $account, $startDate, $endDate);
        });
    }

    public function createFromOrderWithDates(Order $order, Carbon $startDate, Carbon $endDate): Subscription
    {
        return DB::transaction(function () use ($order, $startDate, $endDate) {
            $account = $this->accountAssignmentService->findAvailableAccount($order->product_id);

            if (! $account) {
                throw new \RuntimeException('No available account for product: ' . $order->product->name);
            }

            return $this->createSubscription->execute($order, $account, $startDate, $endDate);
        });
    }

    public function extend(Subscription $subscription, int $days): Subscription
    {
        return DB::transaction(function () use ($subscription, $days) {
            return $this->extendSubscription->execute($subscription, $days);
        });
    }

    public function expire(Subscription $subscription): void
    {
        $this->expireSubscription->execute($subscription);

        \App\Models\ActivityLog::log(
            'subscription_expired',
            'Subscription',
            $subscription->id,
            ['account_id' => $subscription->account_id]
        );
    }

    public function expireSubscriptionAndReleaseAccount(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $this->expireSubscription->execute($subscription);

            if ($subscription->account) {
                $subscription->account->release();
            }
        });
    }
}
