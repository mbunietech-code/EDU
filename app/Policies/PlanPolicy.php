<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Plan;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->is_admin;
    }
}
