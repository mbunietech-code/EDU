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
                        <dt class="mbui-section-label">{{ $order->isToolOrder() ? 'Tool' : 'Product' }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $order->itemName() }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Plan</dt>
                        <dd class="mt-1 text-gray-900">{{ $order->plan ? $order->plan->name . ' (' . $order->plan->durationLabel() . ')' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Amount</dt>
                        <dd class="mt-1 font-bold text-gray-900">TZS {{ number_format($order->amount) }}</dd>
                        <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs font-semibold text-gray-600" />
                    </div>
                    <div>
                        <dt class="mbui-section-label">Confirmed at</dt>
                        <dd class="mt-1 text-gray-900">{{ $order->confirmed_at?->format('d M Y H:i') ?? 'Not confirmed' }}</dd>
                    </div>
                </dl>
            </x-mbui.card>

            @if ($order->isToolOrder())
                <x-mbui.card>
                    <h2 class="mbui-section-label">Tool delivery</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="mbui-section-label">Product key</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $order->tool->license_key ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="mbui-section-label">Delivery status</dt>
                            <dd class="mt-1">
                                @if ($order->isConfirmed())
                                    <x-mbui.badge appearance="success">Delivered to buyer</x-mbui.badge>
                                @else
                                    <span class="text-gray-400">Awaiting payment approval</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </x-mbui.card>
            @endif

            @if ($order->isSoftware())
                <x-mbui.card>
                    <div class="flex items-center justify-between">
                        <h2 class="mbui-section-label">Software delivery</h2>
                    </div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="mbui-section-label">Product key</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $order->product->software_key ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="mbui-section-label">Software file</dt>
                            <dd class="mt-1 text-gray-900">{{ $order->product->software_filename ?? 'None' }}</dd>
                        </div>
                        <div>
                            <dt class="mbui-section-label">Access status</dt>
                            <dd class="mt-1">
                                @if ($order->softwareAccessActive())
                                    <x-mbui.badge appearance="success">Open until {{ $order->software_access_expires_at->format('d M Y H:i') }}</x-mbui.badge>
                                @elseif ($order->softwareAccessLocked())
                                    <x-mbui.badge appearance="danger">Locked</x-mbui.badge>
                                @else
                                    <span class="text-gray-400">Awaiting payment</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if ($order->softwareAccessLocked())
                        <form method="POST" action="{{ route('admin.orders.reopen-access', $order) }}" class="mt-4">
                            @csrf
                            <x-mbui.button type="submit" variant="secondary">Re-open access (20 min)</x-mbui.button>
                        </form>
                    @endif
                </x-mbui.card>
            @endif

            <div>
                <h2 class="mbui-section-label">Payments</h2>
                @forelse ($order->payments as $payment)
                    <div class="mt-3 mbui-card p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $payment->paymentMethodLabel() }} &middot; {{ $payment->transaction_reference }}</p>
                            <p class="text-xs text-gray-500">TZS {{ number_format($payment->amount) }} &middot; {{ $payment->created_at->format('d M Y H:i') }}</p>
                            <x-currency-conversion :amount="$payment->amount" class="mt-1 text-xs font-semibold text-gray-600" />
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