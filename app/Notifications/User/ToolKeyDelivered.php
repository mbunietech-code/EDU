<?php

namespace App\Notifications\User;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ToolKeyDelivered extends Notification implements ShouldQueue
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
            'message' => 'Your payment was approved. Your product key for ' . $this->order->itemName() . ' is now available.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your product key is ready')
            ->line('Your payment for order ' . $this->order->order_number . ' has been approved.')
            ->line('Your product key for ' . $this->order->itemName() . ' is now available on your order page.')
            ->action('Open Order', url('/my-orders/' . $this->order->id))
            ->line('Thank you for using MbunieEduHub!');
    }
}
