<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Notifications\User\OrderCreated;
use App\Notifications\User\PaymentSubmitted;
use App\Notifications\User\PaymentApproved;
use App\Notifications\User\PaymentRejected;
use App\Notifications\User\SubscriptionActivated;
use App\Notifications\User\SoftwareDelivered;
use App\Notifications\User\ExpiryWarning;
use App\Notifications\User\SubscriptionExpired;
use App\Notifications\User\AccessRevoked;
use App\Notifications\Admin\NewOrder;
use App\Notifications\Admin\NewPaymentProof;
use App\Notifications\Admin\ExpiringSubscription;
use App\Notifications\Admin\AccountUnavailable;
use App\Notifications\ChatMessageReceived;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function notifyOrderCreated(User $user, $order): void
    {
        Notification::send($user, new OrderCreated($order));
        $admins = User::where('is_admin', true)->get();
        Notification::send($admins, new NewOrder($order));
    }

    public function notifyPaymentSubmitted(User $user, $payment): void
    {
        Notification::send($user, new PaymentSubmitted($payment));
    }

    public function notifyPaymentApproved(User $user, $payment): void
    {
        Notification::send($user, new PaymentApproved($payment));
    }

    public function notifyPaymentRejected(User $user, $payment): void
    {
        Notification::send($user, new PaymentRejected($payment));
    }

    public function notifySubscriptionActivated(User $user, $subscription): void
    {
        Notification::send($user, new SubscriptionActivated($subscription));
    }

    public function notifySoftwareDelivered(User $user, $order): void
    {
        Notification::send($user, new SoftwareDelivered($order));
    }

    public function notifyExpiryWarning(User $user, $subscription): void
    {
        Notification::send($user, new ExpiryWarning($subscription));
    }

    public function notifySubscriptionExpired(User $user, $subscription): void
    {
        Notification::send($user, new SubscriptionExpired($subscription));
    }

    public function notifyUserAccessRevoked(User $user, $subscription): void
    {
        Notification::send($user, new AccessRevoked($subscription));
    }

    public function notifyAdminNewOrder($order): void
    {
        $admins = User::where('is_admin', true)->get();
        Notification::send($admins, new NewOrder($order));
    }

    public function notifyAdminNewPaymentProof($payment): void
    {
        $admins = User::where('is_admin', true)->get();
        Notification::send($admins, new NewPaymentProof($payment));
    }

    public function notifyAdminExpiringSubscription($subscription): void
    {
        $admins = User::where('is_admin', true)->get();
        Notification::send($admins, new ExpiringSubscription($subscription));
    }

    public function notifyAdminAccountUnavailable(Product $product): void
    {
        $admins = User::where('is_admin', true)->get();
        Notification::send($admins, new AccountUnavailable($product));
    }

    public function notifyUserNewChatMessage(\App\Models\Conversation $conversation): void
    {
        Notification::send($conversation->user, new ChatMessageReceived($conversation, 'Admin'));
    }

    public function notifyAdminsNewChatMessage(\App\Models\Conversation $conversation): void
    {
        $admins = User::where('is_admin', true)->get();
        Notification::send($admins, new ChatMessageReceived($conversation, $conversation->user->name));
    }
}
