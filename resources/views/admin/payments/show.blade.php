<x-layouts.admin title="Payment" header="Payment review">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payment for {{ $payment->order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $payment->user->name }} ({{ $payment->user->email }}) &middot; Submitted {{ $payment->created_at->format('d M Y H:i') }}</p>
        </div>
        <x-mbui.status-badge :status="$payment->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Payment details</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mbui-section-label">Method</dt>
                        <dd class="mt-1 text-gray-900">{{ $payment->paymentMethodLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Reference</dt>
                        <dd class="mt-1 text-gray-900">{{ $payment->transaction_reference }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Amount</dt>
                        <dd class="mt-1 font-bold text-gray-900">TZS {{ number_format($payment->amount) }}</dd>
                        <x-currency-conversion :amount="$payment->amount" class="mt-1 text-xs text-gray-400" />
                    </div>
                    <div>
                        <dt class="mbui-section-label">Order amount</dt>
                        <dd class="mt-1 text-gray-900">TZS {{ number_format($payment->order->amount) }}</dd>
                        <x-currency-conversion :amount="$payment->order->amount" class="mt-1 text-xs text-gray-400" />
                    </div>
                </dl>

                @if ($payment->order->payment_instructions)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h3 class="mbui-section-label">Invoice payment instructions</h3>
                        <p class="mt-2 text-sm whitespace-pre-line text-gray-700">{{ $payment->order->payment_instructions }}</p>
                    </div>
                @endif
            </x-mbui.card>

            @if ($payment->paymentProofs->isNotEmpty())
                <div>
                    <h2 class="mbui-section-label">Payment proof</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        @foreach ($payment->paymentProofs as $proof)
                            <div class="mbui-card overflow-hidden">
                                <a href="{{ route('admin.payments.proof', [$payment, $proof]) }}" target="_blank">
                                    <img src="{{ route('admin.payments.proof', [$payment, $proof]) }}" alt="Payment proof {{ $loop->iteration }}" class="w-full max-h-72 object-cover">
                                </a>
                                @if ($proof->caption)
                                    <p class="px-4 py-2 text-xs text-gray-500">{{ $proof->caption }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-mbui.alert type="warning">No payment proof was submitted with this payment.</x-mbui.alert>
            @endif
        </div>

        <div class="space-y-6">
            @if ($payment->isPending())
                <x-mbui.card>
                    <h2 class="mbui-section-label">Decision</h2>
                    <form method="POST" action="{{ route('admin.payments.approve', $payment) }}" class="mt-4">
                        @csrf
                        <x-mbui.button type="submit" variant="success" class="w-full">Approve payment</x-mbui.button>
                    </form>

                    <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="mt-3 space-y-3">
                        @csrf
                        <textarea name="reason" rows="3" class="mbui-input" placeholder="Rejection reason (shown to user)..."></textarea>
                        <x-mbui.button type="submit" variant="danger" class="w-full">Reject payment</x-mbui.button>
                    </form>

                    <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                        Approving will confirm the order, assign an available account and activate the subscription. This runs atomically.
                    </div>
                </x-mbui.card>
            @else
                <x-mbui.card>
                    <h2 class="mbui-section-label">Review outcome</h2>
                    <div class="mt-3 space-y-3 text-sm">
                        <div class="flex items-center gap-2">
                            <x-mbui.status-badge :status="$payment->status" />
                            <span class="text-gray-500">{{ $payment->reviewed_at?->format('d M Y H:i') ?? '-' }}</span>
                        </div>
                        @if ($payment->reviewer)
                            <p class="text-sm text-gray-600">Reviewed by <span class="font-medium text-gray-900">{{ $payment->reviewer->name }}</span></p>
                        @endif
                        @if ($payment->admin_note)
                            <x-mbui.alert type="info">Note: {{ $payment->admin_note }}</x-mbui.alert>
                        @endif
                    </div>
                </x-mbui.card>
            @endif

            <x-mbui.card>
                <h2 class="mbui-section-label">Order context</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('admin.orders.show', $payment->order) }}" class="mbui-anchor">{{ $payment->order->order_number }}</a>
                        <x-mbui.status-badge :status="$payment->order->status" />
                    </div>
                    @if ($payment->order->subscription)
                        <div class="flex items-center justify-between">
                            <a href="{{ route('admin.subscriptions.show', $payment->order->subscription) }}" class="mbui-anchor">Subscription #{{ $payment->order->subscription->id }}</a>
                            <x-mbui.status-badge :status="$payment->order->subscription->status" />
                        </div>
                    @endif
                </div>
            </x-mbui.card>
        </div>
    </div>

</x-layouts.admin>