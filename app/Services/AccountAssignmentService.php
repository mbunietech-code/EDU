<?php

namespace App\Services;

use App\Actions\Accounts\AssignAccount;
use App\Actions\Accounts\ReleaseAccount;
use App\Models\Account;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class AccountAssignmentService
{
    protected AssignAccount $assignAccount;
    protected ReleaseAccount $releaseAccount;

    public function __construct(AssignAccount $assignAccount, ReleaseAccount $releaseAccount)
    {
        $this->assignAccount = $assignAccount;
        $this->releaseAccount = $releaseAccount;
    }

    public function findAvailableAccount(int $productId): ?Account
    {
        return Account::where('product_id', $productId)
            ->where('status', 'available')
            ->lockForUpdate()
            ->first();
    }

    public function assignAccount(Subscription $subscription): Account
    {
        return $this->assignAccount->execute($subscription);
    }

    public function releaseAccount(Subscription $subscription): void
    {
        $this->releaseAccount->execute($subscription);
    }

    public function findAvailableAccountForOrder(Order $order): ?Account
    {
        return $this->findAvailableAccount($order->product_id);
    }

    public function createSubscriptionWithAccount(
        Order $order,
        string $startDate,
        string $endDate
    ): Subscription {
        return DB::transaction(function () use ($order, $startDate, $endDate) {
            $account = $this->findAvailableAccount($order->product_id);

            if (! $account) {
                throw new \RuntimeException('No available accounts for product: ' . $order->product->name);
            }

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

            return $subscription;
        });
    }
}
