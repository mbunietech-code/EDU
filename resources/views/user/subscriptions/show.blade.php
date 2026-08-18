<x-layouts.user title="Subscription #{{ $subscription->id }}" header="Subscription details">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $subscription->product->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subscription->plan->name }} plan</p>
        </div>
        <x-mbui.status-badge :status="$subscription->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Subscription summary</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mbui-section-label">Product</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $subscription->product->name }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Plan</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->plan->name }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Start date</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->start_date->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Expiry date</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->expiry_date->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Days remaining</dt>
                        <dd class="mt-1 font-bold {{ max($subscription->daysRemaining(), 0) <= 3 ? 'text-red-600' : 'text-emerald-600' }}">{{ max($subscription->daysRemaining(), 0) }} days</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Payment</dt>
                        <dd class="mt-1">
                            @if ($subscription->order->payment)
                                <x-mbui.status-badge :status="$subscription->order->payment->status" />
                            @else
                                <span class="text-gray-500">Not captured</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-mbui.card>

            <x-mbui.card>
                <h2 class="mbui-section-label">Access</h2>
                <div class="mt-3">
                    @if ($subscription->isActive() || $subscription->status === 'expiring_soon')
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                            Your access is <strong>active</strong>. Enjoy {{ $subscription->product->name }} until {{ $subscription->expiry_date->format('d M Y') }}.
                        </div>
                        <div class="mt-4"><a href="{{ route('public.products.show', $subscription->product) }}" class="mbui-anchor text-sm">Redeem / assign access</a></div>
                    @else
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600">
                            Access is currently <strong>{{ str_replace('_', ' ', $subscription->status) }}</strong>.
                            @if ($subscription->isExpired())
                                <a href="{{ route('public.products.show', $subscription->product) }}" class="mbui-anchor">Renew now</a>.
                            @endif
                        </div>
                    @endif
                </div>
            </x-mbui.card>
        </div>

        <div class="space-y-6">
            <div class="mbui-card p-6">
                <h2 class="mbui-section-label">Renewal</h2>
                <p class="mt-2 text-sm text-gray-600">
                    @if ($subscription->status === 'active' || $subscription->status === 'expiring_soon')
                        Renew to extend your access to {{ $subscription->product->name }}.
                    @else
                        This subscription is {{ str_replace('_', ' ', $subscription->status) }}. You can start a new subscription for the product.
                    @endif
                </p>
                <a href="{{ route('public.products.show', $subscription->product) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Renew subscription
                </a>
            </div>

            <div class="mbui-card p-6">
                <h2 class="mbui-section-label">Related order</h2>
                <div class="mt-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $subscription->order->order_number }}</p>
                        <p class="text-xs text-gray-500">TZS {{ number_format($subscription->order->amount) }}</p>
                        <x-currency-conversion :amount="$subscription->order->amount" class="mt-1 text-xs text-gray-400" />
                    </div>
                    <a href="{{ route('user.orders.show', $subscription->order) }}" class="mbui-anchor text-sm">View</a>
                </div>
            </div>
        </div>
    </div>

</x-layouts.user>