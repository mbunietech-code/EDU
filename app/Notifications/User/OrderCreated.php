<?php

namespace App\Notifications\User;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrderCreated extends Notification implements ShouldQueue
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
            'amount' => $this->order->amount,
            'product_name' => $this->order->product->name,
            'message' => 'Your order has been created.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Created - ' . $this->order->order_number)
            ->line('Your order ' . $this->order->order_number . ' has been created.')
            ->line('Amount: ' . number_format($this->order->amount, 2))
            ->action('View Order', url('/my-orders/' . $this->order->id))
            ->line('Thank you for using MbunieEduHub!');
    }
}
