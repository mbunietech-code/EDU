<?php

namespace App\Notifications\User;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentRejected extends Notification implements ShouldQueue
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
            'admin_note' => $this->payment->admin_note,
            'message' => 'Your payment has been rejected. Please review the note and try again.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Payment Rejected')
            ->line('Your payment of ' . number_format($this->payment->amount, 2) . ' for order ' . $this->payment->order->order_number . ' has been rejected.');

        if ($this->payment->admin_note) {
            $mail->line('Reason: ' . $this->payment->admin_note);
        }

        return $mail
            ->action('View Payment', url('/payments/' . $this->payment->id))
            ->line('Please review and submit a new payment.');
    }
}
