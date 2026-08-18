<?php

namespace App\Actions\Accounts;

use App\Models\Subscription;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class RevokeAccount
{
    public function execute(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $account = $subscription->account;

            if ($account) {
                $account->update(['status' => 'suspended']);

                \App\Models\ActivityLog::log(
                    'account_revoked',
                    'Account',
                    $account->id,
                    ['subscription_id' => $subscription->id]
                );
            }
        });
    }
}
