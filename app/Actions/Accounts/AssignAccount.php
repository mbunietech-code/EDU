<?php

namespace App\Actions\Accounts;

use App\Models\Subscription;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class AssignAccount
{
    public function execute(Subscription $subscription): Account
    {
        return DB::transaction(function () use ($subscription) {
            $account = Account::where('product_id', $subscription->product_id)
                ->where('status', 'available')
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new \RuntimeException('No available account for product ID: ' . $subscription->product_id);
            }

            $account->update(['status' => 'assigned']);

            \App\Models\ActivityLog::log(
                'account_assigned',
                'Account',
                $account->id,
                ['subscription_id' => $subscription->id]
            );

            return $account;
        });
    }
}
