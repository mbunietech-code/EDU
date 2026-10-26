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

    /** Name and email. Admin accounts only by a super admin (an admin cannot take over another). */
    public function update(User $user, User $target): bool
    {
        return $user->is_admin && (! $target->is_admin || $user->isSuperAdmin() || $target->id === $user->id);
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
