<?php

namespace App\Notifications\Admin;

use App\Models\GatewayPayment;
use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells admins an order was paid online so they can process it. Sent right
 * away, not queued.
 */
class OnlinePaymentReceived extends Notification
{
    public function __construct(public Payment $payment, public GatewayPayment $gatewayPayment)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    protected function needsReview(): bool
    {
        return ! $this->payment->isApproved();
    }

    public function toDatabase($notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'order_number' => $this->payment->order->order_number,
            'user_name' => $this->payment->user->name,
            'amount' => $this->payment->amount,
            'gateway' => $this->gatewayPayment->gateway,
            'message' => ($this->needsReview() ? 'Online payment needs review: ' : 'New online payment received: ')
                .'TZS '.number_format((float) $this->payment->amount).' for order '.$this->payment->order->order_number,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->payment->order;
        $mail = (new MailMessage)
            ->subject(($this->needsReview() ? 'Review needed: ' : 'New payment: ').'TZS '.number_format((float) $this->payment->amount).' · Order '.$order->order_number)
            ->greeting($this->needsReview() ? 'An online payment needs your review' : 'New online payment received')
            ->line('Customer: '.$this->payment->user->name.' ('.$this->payment->user->email.')')
            ->line('Order: '.$order->order_number)
            ->line('Amount: TZS '.number_format((float) $this->payment->amount, 2).' via '.$this->payment->paymentMethodLabel())
            ->line('Reference: '.$this->payment->transaction_reference);

        if ($this->needsReview()) {
            $mail->line('Why: '.($this->payment->admin_note ?: 'The payment could not be applied automatically.'));
        } else {
            $mail->line('The payment was verified with the provider and the order is marked paid. Please process the order.');
        }

        return $mail->action('Open order', route('admin.orders.show', $order));
    }
}
