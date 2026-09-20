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
use App\Support\Permissions;
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

        // Super admins bypass every permission check.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // One gate per fine-grained admin permission.
        foreach (Permissions::keys() as $key) {
            Gate::define($key, fn (User $user) => $user->hasPermission($key));
        }

        // These two are ALWAYS super-admin only — never grantable to a
        // normal admin, not even via the "unrestricted" (null) fallback.
        Gate::define('database.access', fn (User $user) => $user->isSuperAdmin());
        Gate::define('team.manage', fn (User $user) => $user->isSuperAdmin());
        Gate::define('ai.access', fn (User $user) => $user->isSuperAdmin());
    }
}
