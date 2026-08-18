<?php

namespace App\Notifications\Admin;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExpiringSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'user_name' => $this->subscription->user->name,
            'product_name' => $this->subscription->product->name,
            'expiry_date' => $this->subscription->expiry_date->toDateString(),
            'message' => 'Subscription expiring soon for user: ' . $this->subscription->user->name,
        ];
    }
}
