<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Payment · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f9fafb; color: #111827; }
        main { max-width: 420px; margin: 12vh auto 0; padding: 32px 24px; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
        .icon { width: 56px; height: 56px; margin: 0 auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .ok { background: #d1fae5; color: #059669; } .wait { background: #e0e7ff; color: #4f46e5; } .bad { background: #fee2e2; color: #dc2626; }
        h1 { font-size: 20px; margin: 20px 0 8px; } p { color: #4b5563; font-size: 15px; line-height: 1.5; margin: 0 0 8px; }
        small { display: block; margin-top: 20px; color: #9ca3af; font-size: 12px; }
    </style>
</head>
<body>
<main>
    @if ($payment->isSuccessful())
        <div class="icon ok">&#10003;</div>
        <h1>Payment received</h1>
        <p>Thank you! Your order is confirmed and your receipt is on its way by email.</p>
        <p><strong>You can close this page and go back to the {{ config('app.name') }} app.</strong></p>
    @elseif ($cancelled || $payment->status === 'failed')
        <div class="icon bad">&#10005;</div>
        <h1>Payment not completed</h1>
        <p>{{ $payment->message ?: 'The payment was cancelled.' }}</p>
        <p>Go back to the {{ config('app.name') }} app to try again or choose another way to pay.</p>
    @else
        <div class="icon wait">&#8987;</div>
        <h1>Finishing your payment</h1>
        <p>The payment is still being confirmed. Go back to the {{ config('app.name') }} app: it updates automatically.</p>
    @endif
    <small>Ref {{ $payment->external_id }}</small>
</main>
</body>
</html>
