<?php

namespace App\Actions\Accounts;

use App\Models\Subscription;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class ReleaseAccount
{
    public function execute(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $account = $subscription->account;

            if ($account && $account->status === 'assigned') {
                $account->update(['status' => 'available']);

                \App\Models\ActivityLog::log(
                    'account_released',
                    'Account',
                    $account->id,
                    ['subscription_id' => $subscription->id]
                );
            }
        });
    }
}
