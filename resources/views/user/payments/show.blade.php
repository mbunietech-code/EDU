<x-layouts.user title="Payment" header="Payment details">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payment for {{ $payment->order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">Submitted on {{ $payment->created_at->format('d M Y H:i') }}</p>
        </div>
        <x-mbui.status-badge :status="$payment->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Payment details</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mbui-section-label">Order</dt>
                        <dd class="mt-1">
                            <a href="{{ route('user.orders.show', $payment->order) }}" class="mbui-anchor">{{ $payment->order->order_number }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Amount</dt>
                        <dd class="mt-1 font-bold text-gray-900">TZS {{ number_format($payment->amount) }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Payment method</dt>
                        <dd class="mt-1 text-gray-900">{{ $payment->paymentMethodLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Transaction reference</dt>
                        <dd class="mt-1 text-gray-900">{{ $payment->transaction_reference }}</dd>
                    </div>
                </dl>

                @if ($payment->admin_note)
                    <div class="mt-4">
                        <x-mbui.alert type="{{ $payment->isApproved() ? 'info' : 'warning' }}">
                            <span class="font-semibold">Admin note:</span> {{ $payment->admin_note }}
                        </x-mbui.alert>
                    </div>
                @endif
            </x-mbui.card>

            @if ($payment->paymentProofs->isNotEmpty())
                <div>
                    <h2 class="mbui-section-label">Payment proof</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        @foreach ($payment->paymentProofs as $proof)
                            <div class="mbui-card overflow-hidden">
                                <img src="{{ route('user.payments.proof', [$payment, $proof]) }}" alt="Payment proof {{ $loop->iteration }}" class="w-full max-h-64 object-cover">
                                @if ($proof->caption)
                                    <p class="px-4 py-2 text-xs text-gray-500">{{ $proof->caption }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div>
            <div class="mbui-card p-6">
                <h2 class="mbui-section-label">Verification status</h2>

                <div class="mt-4 space-y-3">
                    <div class="flex items-center gap-3 text-sm">
                        <span class="h-8 w-8 shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </span>
                        <span class="{{ $payment->paymentProofs->isNotEmpty() ? 'text-gray-900 font-medium' : 'text-gray-400' }}">Proof submitted</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="h-8 w-8 shrink-0 rounded-full {{ $payment->status !== 'pending' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }} flex items-center justify-center">
                            @if ($payment->status === 'pending')
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            @else
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            @endif
                        </span>
                        <span class="{{ $payment->status !== 'pending' ? 'text-gray-900 font-medium' : 'text-gray-400' }}">Reviewed</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="h-8 w-8 shrink-0 rounded-full {{ $payment->isApproved() ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </span>
                        <span class="{{ $payment->isApproved() ? 'text-gray-900 font-medium' : 'text-gray-400' }}">Approved</span>
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-4">
                    @if ($payment->isPending())
                        <p class="text-sm text-amber-600 font-medium">Awaiting admin verification. This usually happens within business hours.</p>
                    @elseif ($payment->isApproved())
                        <p class="text-sm text-emerald-600 font-medium">Payment approved. Order confirmed.</p>
                    @else
                        <p class="text-sm text-red-600 font-medium">Payment rejected. Please submit a new payment.</p>
                        @if ($payment->order->isPending())
                            <a href="{{ route('user.payments.create', $payment->order) }}" class="mt-3 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                Submit new payment
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

</x-layouts.user>