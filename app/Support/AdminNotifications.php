<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Admin\AccountUnavailable;
use App\Notifications\Admin\ErrorOccurred;
use App\Notifications\Admin\ExpiringSubscription;
use App\Notifications\Admin\NewContactMessage;
use App\Notifications\Admin\NewOrder;
use App\Notifications\Admin\NewPaymentProof;
use App\Notifications\Admin\OnlinePaymentReceived;
use App\Notifications\Admin\ResearchSubmitted;
use Illuminate\Support\Collection;

/**
 * Who receives, and who may see, the staff notifications (new orders,
 * payments, errors…). Each one goes only to admins holding the permission
 * for that area, and is never shown to a customer — not even one who used
 * to be an admin and still has old ones stored.
 */
final class AdminNotifications
{
    /** Notification class => permission needed to receive and see it. */
    public const PERMISSIONS = [
        NewOrder::class => 'orders.view',
        NewPaymentProof::class => 'payments.view',
        OnlinePaymentReceived::class => 'payments.view',
        NewContactMessage::class => 'contact_messages.view',
        ResearchSubmitted::class => 'research.view',
        ExpiringSubscription::class => 'subscriptions.view',
        AccountUnavailable::class => 'accounts.view',
        ErrorOccurred::class => 'error_logs.view',
        \App\Notifications\Admin\SecurityAlert::class => 'settings.manage',
    ];

    /**
     * Admins allowed to get a notification needing $permission.
     *
     * @return Collection<int,User>
     */
    public static function recipients(string $permission): Collection
    {
        return User::query()
            ->where('is_admin', true)
            ->get()
            ->filter(fn (User $admin) => $admin->hasPermission($permission))
            ->values();
    }

    /** @return Collection<int,User> */
    public static function recipientsFor(string $notificationClass): Collection
    {
        return self::recipients(self::PERMISSIONS[$notificationClass]);
    }

    /**
     * Staff notification types this user must not see: all of them for a
     * customer, the areas they lack permission for for a limited admin.
     *
     * @return list<string>
     */
    public static function hiddenFor(User $user): array
    {
        $hidden = [];
        foreach (self::PERMISSIONS as $class => $permission) {
            // is_admin first: a demoted admin whose old role still says super_admin is a customer now.
            if (! $user->is_admin || ! $user->hasPermission($permission)) {
                $hidden[] = $class;
            }
        }

        return $hidden;
    }
}
