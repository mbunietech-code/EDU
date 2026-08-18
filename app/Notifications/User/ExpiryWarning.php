<?php

namespace App\Notifications\User;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExpiryWarning extends Notification implements ShouldQueue
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
            'expiry_date' => $this->subscription->expiry_date->toDateString(),
            'days_remaining' => $this->subscription->daysRemaining(),
            'message' => 'Your subscription is expiring soon.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Expiring Soon')
            ->line('Your subscription to ' . $this->subscription->product->name . ' will expire on ' . $this->subscription->expiry_date->toDateString() . '.')
            ->line('Days remaining: ' . $this->subscription->daysRemaining())
            ->action('Renew Subscription', url('/my-subscriptions'))
            ->line('Thank you for using MBUNIETECH!');
    }
}
