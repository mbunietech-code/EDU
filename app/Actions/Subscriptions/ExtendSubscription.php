<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExtendSubscription
{
    public function execute(Subscription $subscription, int $days): Subscription
    {
        return DB::transaction(function () use ($subscription, $days) {
            $newExpiry = Carbon::parse($subscription->expiry_date, config('app.timezone'))
                ->addDays($days);

            $subscription->update([
                'expiry_date' => $newExpiry,
                'status' => 'active',
            ]);

            \App\Models\ActivityLog::log(
                'subscription_extended',
                'Subscription',
                $subscription->id,
                ['days_added' => $days, 'new_expiry_date' => $newExpiry->toDateString()]
            );

            return $subscription->fresh();
        });
    }
}
