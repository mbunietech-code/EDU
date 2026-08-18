<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Subscription;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->is_admin || $subscription->user_id === $user->id;
    }

    public function extend(User $user, Subscription $subscription): bool
    {
        return $user->is_admin || $subscription->user_id === $user->id;
    }

    public function revoke(User $user, Subscription $subscription): bool
    {
        return $user->is_admin;
    }

    public function expire(User $user, Subscription $subscription): bool
    {
        return $user->is_admin;
    }

    public function suspend(User $user, Subscription $subscription): bool
    {
        return $user->is_admin;
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->is_admin || $subscription->user_id === $user->id;
    }
}
