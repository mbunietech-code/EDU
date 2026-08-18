<?php

namespace App\Notifications\User;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'product_name' => $this->subscription->product->name,
            'plan_name' => $this->subscription->plan->name,
            'start_date' => $this->subscription->start_date->toDateString(),
            'expiry_date' => $this->subscription->expiry_date->toDateString(),
            'message' => 'Your subscription has been activated.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Activated')
            ->line('Your subscription to ' . $this->subscription->product->name . ' (' . $this->subscription->plan->name . ') has been activated.')
            ->line('Valid from: ' . $this->subscription->start_date->toDateString())
            ->line('Valid until: ' . $this->subscription->expiry_date->toDateString())
            ->action('View Subscription', url('/my-subscriptions'))
            ->line('Thank you for using MBUNIETECH!');
    }
}
