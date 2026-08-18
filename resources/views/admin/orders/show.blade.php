<x-layouts.admin title="Order" header="Order details">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $order->user->name }} ({{ $order->user->email }}) &middot; {{ $order->created_at->format('d M Y H:i') }}</p>
        </div>
        <x-mbui.status-badge :status="$order->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Order details</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mbui-section-label">Product</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $order->product->name }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Plan</dt>
                        <dd class="mt-1 text-gray-900">{{ $order->plan->name }} ({{ $order->plan->durationLabel() }})</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Amount</dt>
                        <dd class="mt-1 font-bold text-gray-900">TZS {{ number_format($order->amount) }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Confirmed at</dt>
                        <dd class="mt-1 text-gray-900">{{ $order->confirmed_at?->format('d M Y H:i') ?? 'Not confirmed' }}</dd>
                    </div>
                </dl>
            </x-mbui.card>

            <div>
                <h2 class="mbui-section-label">Payments</h2>
                @forelse ($order->payments as $payment)
                    <div class="mt-3 mbui-card p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $payment->paymentMethodLabel() }} &middot; {{ $payment->transaction_reference }}</p>
                            <p class="text-xs text-gray-500">TZS {{ number_format($payment->amount) }} &middot; {{ $payment->created_at->format('d M Y H:i') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-mbui.status-badge :status="$payment->status" />
                            <a href="{{ route('admin.payments.show', $payment) }}" class="mbui-anchor text-sm">Review</a>
                        </div>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-gray-500">No payments submitted for this order.</p>
                @endforelse
            </div>

            @if ($order->subscription)
                <x-mbui.card>
                    <h2 class="mbui-section-label">Linked subscription</h2>
                    <div class="mt-3">
                        <a href="{{ route('admin.subscriptions.show', $order->subscription) }}" class="mbui-anchor text-sm">Subscription #{{ $order->subscription->id }} &rarr;</a>
                        <span class="ml-2"><x-mbui.status-badge :status="$order->subscription->status" /></span>
                    </div>
                </x-mbui.card>
            @endif
        </div>

        <div>
            <x-mbui.card class="p-6">
                <h2 class="mbui-section-label">Customer</h2>
                <div class="mt-3 flex items-center gap-3">
                    <span class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center text-sm font-semibold text-white">
                        {{ strtoupper(substr($order->user->name, 0, 1)) }}
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $order->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $order->user->email }}</p>
                    </div>
                </div>
                <a href="{{ route('admin.users.show', $order->user) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    View user history
                </a>
            </x-mbui.card>
        </div>
    </div>

</x-layouts.admin>