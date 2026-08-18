<?php

namespace App\Services;

use App\Actions\Subscriptions\ExpireSubscription;
use App\Models\Subscription;
use App\Notifications\User\ExpiryWarning;
use App\Notifications\User\SubscriptionExpired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class ExpiryService
{
    protected ExpireSubscription $expireSubscription;
    protected int $warningThresholdDays;

    public function __construct(ExpireSubscription $expireSubscription)
    {
        $this->expireSubscription = $expireSubscription;
        $this->warningThresholdDays = (int) config('app.expiry_warning_days', 3);
    }

    public function checkExpiringSoon(): void
    {
        $threshold = Carbon::now(config('app.timezone'))
            ->addDays($this->warningThresholdDays)
            ->toDateString();

        $expiringSoon = Subscription::where('status', 'active')
            ->whereDate('expiry_date', '<=', $threshold)
            ->whereDate('expiry_date', '>=', now(config('app.timezone'))->toDateString())
            ->get();

        foreach ($expiringSoon as $subscription) {
            $subscription->update(['status' => 'expiring_soon']);

            Notification::send($subscription->user, new ExpiryWarning($subscription));

            \App\Models\ActivityLog::log(
                'subscription_expiring_soon',
                'Subscription',
                $subscription->id,
                ['expiry_date' => $subscription->expiry_date->toDateString()]
            );
        }
    }

    public function checkExpired(): void
    {
        $now = Carbon::now(config('app.timezone'))->toDateString();

        $expired = Subscription::where('status', 'active')
            ->whereDate('expiry_date', '<', $now)
            ->get();

        foreach ($expired as $subscription) {
            DB::transaction(function () use ($subscription) {
                $this->expireSubscription->execute($subscription);

                if ($subscription->account) {
                    $subscription->account->release();
                }

                Notification::send($subscription->user, new SubscriptionExpired($subscription));

                \App\Models\ActivityLog::log(
                    'subscription_expired',
                    'Subscription',
                    $subscription->id,
                    ['account_id' => $subscription->account_id, 'expiry_date' => $subscription->expiry_date->toDateString()]
                );
            });
        }
    }

    public function checkExpiringSoonForScheduler(): void
    {
        $this->checkExpiringSoon();
    }

    public function checkExpiredForScheduler(): void
    {
        $this->checkExpired();
    }
}
