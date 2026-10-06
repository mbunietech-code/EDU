<?php

namespace App\Notifications\User;

use App\Models\GatewayPayment;
use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Receipt for an online (AzamPay / ClickPesa / PayPal) payment. Sent right
 * away, not queued, so it arrives even when no queue worker is running.
 */
class PaymentReceipt extends Notification
{
    public function __construct(public Payment $payment, public GatewayPayment $gatewayPayment)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public static function receiptNumber(Payment $payment): string
    {
        return 'RCPT-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'order_number' => $this->payment->order->order_number,
            'amount' => $this->payment->amount,
            'receipt_number' => self::receiptNumber($this->payment),
            'message' => 'Payment of TZS '.number_format((float) $this->payment->amount).' received for order '.$this->payment->order->order_number.'. Your receipt was sent by email.',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->payment->order;
        $confirmed = $this->payment->isApproved();

        return (new MailMessage)
            ->subject('Payment receipt '.self::receiptNumber($this->payment).' · Order '.$order->order_number)
            ->markdown('emails.payments.receipt', [
                'payment' => $this->payment,
                'gatewayPayment' => $this->gatewayPayment,
                'order' => $order,
                'item' => $order->tool?->name ?? trim(($order->product?->name ?? 'Order').' '.($order->plan?->name ? '· '.$order->plan->name : '')),
                'receiptNumber' => self::receiptNumber($this->payment),
                'methodLabel' => $this->payment->paymentMethodLabel(),
                'confirmed' => $confirmed,
                'customerName' => $notifiable->name,
                'receiptUrl' => URL::temporarySignedRoute('signed.orders.receipt', now()->addDays(30), ['order' => $order->id]),
                'orderUrl' => route('user.orders.show', $order),
            ]);
    }
}
