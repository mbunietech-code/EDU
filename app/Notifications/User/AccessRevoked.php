<?php

namespace App\Notifications\User;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AccessRevoked extends Notification implements ShouldQueue
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
            'message' => 'Your access has been revoked.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Access Revoked')
            ->line('Your access to ' . $this->subscription->product->name . ' has been revoked.')
            ->line('Please contact support for assistance.')
            ->action('Contact Support', url('/contact'));
    }
}
