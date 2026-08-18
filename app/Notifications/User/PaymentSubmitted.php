<?php

namespace App\Notifications\User;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'order_number' => $this->payment->order->order_number,
            'amount' => $this->payment->amount,
            'message' => 'Your payment has been submitted and is pending review.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Submitted')
            ->line('Your payment of ' . number_format($this->payment->amount, 2) . ' for order ' . $this->payment->order->order_number . ' has been submitted.')
            ->line('Status: Pending review')
            ->action('View Payment', url('/payments/' . $this->payment->id))
            ->line('Thank you for using MbunieEduHub!');
    }
}
