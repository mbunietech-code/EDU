<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->is_admin || $payment->user_id === $user->id;
    }

    public function approve(User $user): bool
    {
        return $user->is_admin;
    }

    public function reject(User $user): bool
    {
        return $user->is_admin;
    }
}
