<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Product;
use App\Models\Plan;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Policies\UserPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PlanPolicy;
use App\Policies\AccountPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected array $policies = [
        User::class => UserPolicy::class,
        Product::class => ProductPolicy::class,
        Plan::class => PlanPolicy::class,
        Account::class => AccountPolicy::class,
        Order::class => OrderPolicy::class,
        Payment::class => PaymentPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('admin-access', fn (User $user) => $user->is_admin);
    }
}
