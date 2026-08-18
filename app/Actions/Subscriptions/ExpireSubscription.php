<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpireSubscription
{
    public function execute(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update(['status' => 'expired']);

            if ($subscription->account) {
                $subscription->account->release();
            }
        });
    }
}
