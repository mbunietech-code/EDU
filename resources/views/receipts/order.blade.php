<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 30px; }
        .header { width: 100%; border-bottom: 2px solid #4f46e5; padding-bottom: 16px; margin-bottom: 24px; }
        .brand { font-size: 20px; font-weight: bold; color: #4f46e5; }
        .muted { color: #6b7280; }
        .title { font-size: 22px; font-weight: bold; margin-top: 24px; margin-bottom: 4px; }
        table.info { width: 100%; margin-top: 16px; border-collapse: collapse; }
        table.info td { padding: 6px 0; vertical-align: top; }
        table.info td.label { color: #6b7280; width: 160px; }
        table.items { width: 100%; margin-top: 24px; border-collapse: collapse; }
        table.items th { text-align: left; background: #f3f4f6; padding: 8px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; }
        .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; border-bottom: none; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 10px; background: #d1fae5; color: #065f46; font-size: 11px; font-weight: bold; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ config('app.name', 'MbunieEduHub') }}</div>
        <div class="muted">Authorized AI access management</div>
    </div>

    <div class="title">Payment Receipt</div>
    <div class="muted">Receipt for order {{ $order->order_number }}</div>
    <div style="margin-top: 8px;"><span class="status">CONFIRMED</span></div>

    <table class="info">
        <tr>
            <td class="label">Receipt No.</td>
            <td>{{ $order->order_number }}</td>
            <td class="label">Date</td>
            <td>{{ ($order->confirmed_at ?? $order->created_at)->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Billed to</td>
            <td>{{ $order->user->name }}</td>
            <td class="label">Email</td>
            <td>{{ $order->user->email }}</td>
        </tr>
        @if ($payment)
            <tr>
                <td class="label">Payment method</td>
                <td>{{ $payment->paymentMethodLabel() }}</td>
                <td class="label">Reference</td>
                <td>{{ $payment->transaction_reference ?? '—' }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Plan</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $order->itemName() }}</td>
                <td>{{ $order->plan?->name ?? '—' }}</td>
                <td style="text-align: right;">TZS {{ number_format($order->amount) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Total Paid</td>
                <td style="text-align: right;">TZS {{ number_format($order->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        This is a computer-generated receipt from {{ config('app.name', 'MbunieEduHub') }}. No signature required.<br>
        Generated on {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
