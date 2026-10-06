<x-layouts.user title="Confirm Payment" header="Confirm Payment">

    <div class="mx-auto mt-6 max-w-lg">
        <div class="mbui-card p-6 text-center"
            x-data="{
                status: @js($gatewayPayment->status),
                message: @js($gatewayPayment->message),
                timer: null,
                async poll() {
                    try {
                        const res = await fetch(@js(route('user.payments.mobile.status', [$order, $gatewayPayment])), { headers: { 'Accept': 'application/json' } });
                        if (! res.ok) return;
                        const data = await res.json();
                        this.status = data.status;
                        this.message = data.message;
                        if (data.redirect) { clearInterval(this.timer); setTimeout(() => window.location.href = data.redirect, 1500); }
                        if (data.status === 'failed') clearInterval(this.timer);
                    } catch (e) {}
                },
                init() {
                    if (this.status === 'pending' || this.status === 'expired') this.timer = setInterval(() => this.poll(), 4000);
                },
            }">

            <template x-if="status === 'pending'">
                <div>
                    <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></div>
                    @if ($gatewayPayment->gateway === 'paypal')
                        <h1 class="mt-5 text-lg font-semibold text-gray-900">Confirming your PayPal payment</h1>
                        <p class="mt-2 text-sm text-gray-600">
                            PayPal is finalising your payment of
                            <span class="font-semibold">{{ $gatewayPayment->charged_currency }} {{ number_format((float) $gatewayPayment->charged_amount, 2) }}</span>.
                            You can leave this page; your order is confirmed automatically once PayPal finishes.
                        </p>
                    @else
                        <h1 class="mt-5 text-lg font-semibold text-gray-900">Check your phone</h1>
                        <p class="mt-2 text-sm text-gray-600">
                            A payment request of <span class="font-semibold">TZS {{ number_format($gatewayPayment->amount) }}</span>
                            was sent to <span class="font-mono font-semibold">{{ $gatewayPayment->phone }}</span>.
                            Enter your mobile money PIN to approve it.
                        </p>
                    @endif
                    <p class="mt-3 text-xs text-gray-400">This page updates by itself. The request expires after about {{ $timeoutMinutes }} minutes.</p>
                </div>
            </template>

            <template x-if="status === 'success'">
                <div>
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600">&#10003;</div>
                    <h1 class="mt-5 text-lg font-semibold text-gray-900">Payment received</h1>
                    <p class="mt-2 text-sm text-gray-600">Thank you! Your order is confirmed. Taking you to your order...</p>
                </div>
            </template>

            <template x-if="status === 'failed'">
                <div>
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-2xl text-red-600">&#10005;</div>
                    <h1 class="mt-5 text-lg font-semibold text-gray-900">Payment not completed</h1>
                    <p class="mt-2 text-sm text-gray-600" x-text="message || 'The payment was cancelled or declined.'"></p>
                    <a href="{{ route('user.payments.create', $order) }}" class="mt-5 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Try again</a>
                </div>
            </template>

            <template x-if="status === 'expired'">
                <div>
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-600">!</div>
                    <h1 class="mt-5 text-lg font-semibold text-gray-900">No confirmation yet</h1>
                    <p class="mt-2 text-sm text-gray-600">We did not get a reply in time. If money left your account, keep this page open for a moment: it is still checking. Otherwise, try again.</p>
                    <a href="{{ route('user.payments.create', $order) }}" class="mt-5 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Try again</a>
                </div>
            </template>

            <p class="mt-6 text-xs text-gray-400">Paid via {{ $gatewayLabel }} &middot; Ref {{ $gatewayPayment->external_id }}</p>
        </div>

        <p class="mt-4 text-center text-sm"><a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor">Back to order</a></p>
    </div>

</x-layouts.user>
