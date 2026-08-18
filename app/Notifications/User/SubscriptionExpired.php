<?php

namespace App\Notifications\User;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionExpired extends Notification implements ShouldQueue
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
            'message' => 'Your subscription has expired. Your access has been revoked.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Expired')
            ->line('Your subscription to ' . $this->subscription->product->name . ' has expired.')
            ->line('Your access has been revoked.')
            ->action('Renew Subscription', url('/products'))
            ->line('Thank you for using MbunieEduHub!');
    }
}
