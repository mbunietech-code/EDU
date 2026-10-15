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

    /**
     * Approve a renewal order. While the old subscription still holds its
     * account, it is extended (from its end date, or today if that passed) and
     * moves to the renewal order. Once it has expired, a new subscription
     * starts today, on the same account when that one is still free.
     */
    public function renewFromOrder(Order $order): Subscription
    {
        return DB::transaction(function () use ($order) {
            $previous = $order->renewsOrder?->subscription;
            $tz = config('app.timezone');
            $today = Carbon::now($tz);

            if ($previous && $previous->status !== 'expired' && $previous->account_id) {
                $from = $previous->expiry_date && $previous->expiry_date->gt($today)
                    ? Carbon::parse($previous->expiry_date, $tz)
                    : $today;
                $endDate = $order->plan->isLifetime()
                    ? Carbon::parse('2099-12-31', $tz)
                    : $from->copy()->addDays($order->plan->duration_days);

                $previous->update([
                    'order_id' => $order->id,
                    'plan_id' => $order->plan_id,
                    'expiry_date' => $endDate,
                    'status' => 'active',
                ]);

                \App\Models\ActivityLog::log('subscription_renewed', 'Subscription', $previous->id, [
                    'order_id' => $order->id,
                    'new_expiry_date' => $endDate->toDateString(),
                ]);

                return $previous->fresh();
            }

            $account = $previous?->account_id
                ? Account::whereKey($previous->account_id)->assignable()->lockForUpdate()->first()
                : null;
            $account ??= $this->accountAssignmentService->findAvailableAccount($order->product_id);

            if (! $account) {
                throw new \RuntimeException('No available account for product: ' . $order->product->name);
            }

            $endDate = $order->plan->isLifetime()
                ? Carbon::parse('2099-12-31', $tz)
                : $today->copy()->addDays($order->plan->duration_days);

            return $this->createSubscription->execute($order, $account, $today, $endDate);
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
