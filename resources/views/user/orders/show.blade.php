<x-layouts.user title="Order {{ $order->order_number }}" header="Order {{ $order->order_number }}">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Order {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">Placed on {{ $order->created_at->format('d M Y H:i') }}</p>
        </div>
        <x-mbui.status-badge :status="$order->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Order summary</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mbui-section-label">Product</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $order->product->name }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Plan</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $order->plan->name }} ({{ $order->plan->duration_days }} days)</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Amount</dt>
                        <dd class="mt-1 font-bold text-gray-900">TZS {{ number_format($order->amount) }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Status</dt>
                        <dd class="mt-1"><x-mbui.status-badge :status="$order->status" /></dd>
                    </div>
                </dl>
            </x-mbui.card>

            @if ($order->payment_instructions)
                <x-mbui.card>
                    <h2 class="mbui-section-label">Payment instructions</h2>
                    <p class="mt-3 text-sm whitespace-pre-line text-gray-700">{{ $order->payment_instructions }}</p>
                </x-mbui.card>
            @endif

            @if ($order->subscription)
                <x-mbui.card>
                    <h2 class="mbui-section-label">Linked subscription</h2>
                    <div class="mt-3">
                        <a href="{{ route('user.subscriptions.show', $order->subscription) }}" class="mbui-anchor text-sm">
                            Subscription #{{ $order->subscription->id }} &rarr;
                        </a>
                        <span class="ml-2"><x-mbui.status-badge :status="$order->subscription->status" /></span>
                    </div>
                </x-mbui.card>
            @endif

            <div>
                <h2 class="mbui-section-label">Payments</h2>
                @foreach ($order->payments as $payment)
                    <div class="mt-3 mbui-card p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Payment via {{ $payment->payment_method }}</p>
                            <p class="text-xs text-gray-500">Ref: {{ $payment->transaction_reference }} &middot; {{ $payment->created_at->format('d M Y H:i') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-mbui.status-badge :status="$payment->status" />
                            <a href="{{ route('user.payments.show', $payment) }}" class="mbui-anchor text-sm">View</a>
                        </div>
                    </div>
                @endforeach
                @if ($order->isPending())
                    <div class="mt-4">
                        <a href="{{ route('user.payments.create', $order) }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            Make payment
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <div class="mbui-card p-6">
                <h2 class="mbui-section-label">Next steps</h2>
                <ol class="mt-4 space-y-4 text-sm text-gray-600">
                    <li>
                        <span class="font-semibold text-gray-900">1. Make payment</span><br>
                        Follow your preferred payment method and keep the reference.
                    </li>
                    <li>
                        <span class="font-semibold text-gray-900">2. Upload proof</span><br>
                        Submit the proof image with the transaction reference.
                    </li>
                    <li>
                        <span class="font-semibold text-gray-900">3. Wait for approval</span><br>
                        Our team verifies and activates your subscription.
                    </li>
                </ol>
            </div>
        </div>
    </div>

</x-layouts.user>