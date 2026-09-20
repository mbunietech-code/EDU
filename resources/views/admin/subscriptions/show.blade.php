<x-layouts.admin title="Subscription" header="Subscription management">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Subscription #{{ $subscription->id }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subscription->user->name }} ({{ $subscription->user->email }})</p>
        </div>
        <x-mbui.status-badge :status="$subscription->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Subscription details</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="mbui-section-label">Product</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $subscription->product->name }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Plan</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->plan->name }} ({{ $subscription->plan->durationLabel() }})</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Start</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->start_date->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Expiry</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->expiry_date->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Account</dt>
                        <dd class="mt-1">
                            @if ($subscription->account)
                                <a href="{{ route('admin.accounts.show', $subscription->account) }}" class="mbui-anchor">{{ $subscription->account->name }}</a>
                                <span class="ml-1"><x-mbui.status-badge :status="$subscription->account->status" /></span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Device</dt>
                        <dd class="mt-1 text-gray-900">{{ $subscription->order->device ?: 'Not set' }} <a href="{{ route('admin.orders.edit', $subscription->order) }}" class="mbui-anchor ml-2 text-xs">Edit</a></dd>
                    </div>
                    <div>
                        <dt class="mbui-section-label">Order</dt>
                        <dd class="mt-1">
                            <a href="{{ route('admin.orders.show', $subscription->order) }}" class="mbui-anchor">{{ $subscription->order->order_number }}</a>
                        </dd>
                    </div>
                </dl>
            </x-mbui.card>

            <x-mbui.card>
                <h2 class="mbui-section-label">Account</h2>
                <div class="mt-3">
                    @if ($subscription->account)
                        <a href="{{ route('admin.accounts.show', $subscription->account) }}" class="mbui-anchor text-sm">
                            Manage account credentials &rarr;
                        </a>
                        <p class="mt-2 text-xs text-gray-500">Credentials are encrypted at rest and only decrypted on demand with audit logging.</p>
                    @else
                        <p class="text-sm text-gray-500">No account linked.</p>
                    @endif
                </div>
            </x-mbui.card>
        </div>

        @if ($subscription->isActive() || $subscription->status === 'expiring_soon')
            <div class="lg:col-span-1">
                <x-mbui.card>
                    <h2 class="mbui-section-label">Management actions</h2>
                    <div class="mt-4 space-y-4">
                        <form method="POST" action="{{ route('admin.subscriptions.extend', $subscription) }}" class="flex gap-2">
                            @csrf
                            <input type="number" name="days" min="1" max="3650" value="30" required class="mbui-input !w-24">
                            <x-mbui.button type="submit" variant="success" class="flex-1">Extend (days)</x-mbui.button>
                        </form>

                        @if ($subscription->status === 'active')
                            <form method="POST" action="{{ route('admin.subscriptions.suspend', $subscription) }}">
                                @csrf
                                <x-mbui.button type="submit" variant="warning" class="w-full">Suspend</x-mbui.button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.subscriptions.expire', $subscription) }}">
                            @csrf
                            <x-mbui.button type="submit" variant="danger" class="w-full">Expire &amp; release account</x-mbui.button>
                        </form>

                        <form method="POST" action="{{ route('admin.subscriptions.revoke', $subscription) }}">
                            @csrf
                            <x-mbui.button type="submit" variant="danger" class="w-full">Revoke access</x-mbui.button>
                        </form>
                    </div>
                </x-mbui.card>
            </div>
        @endif
    </div>

</x-layouts.admin>