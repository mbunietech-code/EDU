<x-mail::message>
# Payment received

Hello {{ $customerName }},

Thank you! We have received your payment. Here is your receipt.

<x-mail::table>
| | |
|:--|--:|
| **Receipt no.** | {{ $receiptNumber }} |
| **Order** | {{ $order->order_number }} |
| **Item** | {{ $item }} |
| **Amount paid** | TZS {{ number_format((float) $payment->amount, 2) }} |
@if ($gatewayPayment->charged_currency && $gatewayPayment->charged_currency !== 'TZS')
| **Charged** | {{ $gatewayPayment->charged_currency }} {{ number_format((float) $gatewayPayment->charged_amount, 2) }} |
@endif
| **Paid with** | {{ $methodLabel }}@if ($gatewayPayment->network) ({{ $gatewayPayment->network }})@endif |
@if ($gatewayPayment->phone)
| **From number** | {{ $gatewayPayment->phone }} |
@endif
@if ($gatewayPayment->payer_email)
| **PayPal account** | {{ $gatewayPayment->payer_email }} |
@endif
| **Transaction ref.** | {{ $payment->transaction_reference }} |
| **Date** | {{ ($gatewayPayment->completed_at ?? $payment->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }} |
| **Status** | {{ $confirmed ? 'Paid, order confirmed' : 'Paid, being processed' }} |
</x-mail::table>

@if ($confirmed)
Your order is confirmed and ready in your account.
@else
Your payment is safe. Our team has been notified and will finish processing your order shortly.
@endif

<x-mail::button :url="$receiptUrl">
View / print receipt
</x-mail::button>

You can also see this order any time from [your account]({{ $orderUrl }}).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
