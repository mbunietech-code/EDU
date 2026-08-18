<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewOrder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'user_name' => $this->order->user->name,
            'user_email' => $this->order->user->email,
            'product_name' => $this->order->product->name,
            'amount' => $this->order->amount,
            'message' => 'New order created: ' . $this->order->order_number,
        ];
    }
}
