<?php

namespace App\Notifications\User;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SoftwareDelivered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'expires_at' => $this->order->software_access_expires_at?->toDateTimeString(),
            'message' => 'Your payment was approved. Your software download and product key are available for ' . config('software.access_minutes') . ' minutes.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Software ready for download')
            ->line('Your payment for order ' . $this->order->order_number . ' has been approved.')
            ->line('Your product key and software download are now available. Open your order to download them before access closes (' . config('software.access_minutes') . ' minutes).')
            ->action('Open Order', url('/my-orders/' . $this->order->id))
            ->line('Thank you for using MbunieEduHub!');
    }
}