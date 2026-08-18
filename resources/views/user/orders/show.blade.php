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
                        <dd class="mt-1 font-medium text-gray-900">{{ $order->plan->name }} ({{ $order->plan->durationLabel() }})</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Amount</dt>
                        <dd class="mt-1 font-bold text-gray-900">TZS {{ number_format($order->amount) }}</dd>
                        <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs text-gray-400" />
                    </div>
                    <div>
                        <dt class="mbui-section-label">Status</dt>
                        <dd class="mt-1"><x-mbui.status-badge :status="$order->status" /></dd>
                    </div>
                </dl>
            </x-mbui.card>

            @if ($order->isSoftware())
                <x-mbui.card>
                    <h2 class="mbui-section-label">Software delivery</h2>
                    @if (! $order->isConfirmed())
                        <p class="mt-3 text-sm text-gray-600">Once your payment is approved, your product key and the software download will be revealed here. Access opens for {{ config('software.access_minutes') }} minutes.</p>
                    @elseif ($order->softwareAccessActive())
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-sm font-semibold text-emerald-800">Access active until {{ $order->software_access_expires_at->format('d M Y H:i') }}</p>
                            <div class="mt-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Product key</p>
                                <p class="mt-1 select-all break-all rounded-lg border border-emerald-200 bg-white px-3 py-2 font-mono text-sm text-gray-900">{{ $order->productKey?->key_value ?? 'Assigned by admin' }}</p>
                            </div>
                            @if ($order->product->software_file)
                                <a href="{{ route('user.orders.download-software', $order) }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                    Download {{ $order->product->software_filename ?? 'software' }}
                                </a>
                            @endif
                            <p class="mt-3 text-xs text-emerald-700">Download now. Access closes automatically {{ $order->software_access_expires_at->diffForHumans() }}.</p>
                        </div>
                    @elseif ($order->softwareAccessLocked())
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-semibold text-amber-800">Access expired</p>
                            <p class="mt-1 text-sm text-amber-700">The {{ config('software.access_minutes') }}-minute access window has closed. Contact support to re-open your download.</p>
                        </div>
                    @endif
                </x-mbui.card>
            @endif

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
                            <p class="text-sm font-medium text-gray-900">Payment via {{ $payment->paymentMethodLabel() }}</p>
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