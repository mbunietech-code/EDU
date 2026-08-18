<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, User $target): bool
    {
        return $user->is_admin;
    }

    public function suspend(User $user, User $target): bool
    {
        return $user->is_admin && $target->id !== $user->id;
    }

    public function activate(User $user, User $target): bool
    {
        return $user->is_admin && $target->id !== $user->id;
    }
}
