<x-layouts.user title="Dashboard" header="Dashboard">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Here's an overview of your active access.</p>
        </div>
        <a href="{{ route('public.products.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            Browse AI Tools
        </a>
    </div>

    @if ($activeSubscriptions->isNotEmpty())
        <div class="mt-8">
            <h2 class="mbui-section-label">Your Active Access</h2>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                @foreach ($activeSubscriptions as $subscription)
                    <div class="mbui-card overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $subscription->product->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $subscription->plan->name }} Plan</p>
                            </div>
                            <x-mbui.status-badge :status="$subscription->status" />
                        </div>
                        <dl class="grid grid-cols-2 gap-4 px-6 py-5 text-sm">
                            <div>
                                <dt class="mbui-section-label">Started</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $subscription->start_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="mbui-section-label">Expires</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $subscription->expiry_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="mbui-section-label">Days remaining</dt>
                                <dd class="mt-1 font-bold text-lg {{ $subscription->daysRemaining() <= 3 ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ max($subscription->daysRemaining(), 0) }} days
                                </dd>
                            </div>
                            <div>
                                <dt class="mbui-section-label">Payment status</dt>
                                <dd class="mt-1">
                                    <x-mbui.status-badge :status="$subscription->order->payment?->status ?? 'pending'" />
                                </dd>
                            </div>
                        </dl>
                        <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                            <a href="{{ route('user.subscriptions.show', $subscription) }}" class="mbui-anchor text-sm">View details</a>
                            <a href="{{ route('public.products.show', $subscription->product) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Renew</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($featuredProducts->isNotEmpty())
        <div class="mt-10">
            <div class="mbui-page-header">
                <div>
                    <h2 class="mbui-section-label">Popular AI Tools</h2>
                    <p class="mt-1 text-sm text-gray-500">Trusted by users across Tanzania</p>
                </div>
                <a href="{{ route('public.products.index') }}" class="mbui-anchor text-sm">View all</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredProducts as $product)
                    <a href="{{ route('public.products.show', $product) }}" class="mbui-card group flex items-center gap-3 p-3 transition hover:shadow-md">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                            @if ($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-contain p-1.5">
                            @else
                                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $product->name }}</h3>
                            <p class="mt-1 text-sm font-bold text-gray-900">TZS {{ number_format($product->price) }}</p>
                            <p class="text-xs font-semibold text-gray-600">
                                &asymp; ${{ number_format($product->price * $rates['USD'], 2) }} USD &middot; &asymp; &yen;{{ number_format($product->price * $rates['CNY'], 2) }} CNY
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($pendingOrders->isNotEmpty())
        <div class="mt-10">
            <h2 class="mbui-section-label">Pending Orders</h2>
            <x-mbui.table class="mt-3" :title="'Pending Orders'">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Order</th>
                        <th class="mbui-th">Product</th>
                        <th class="mbui-th">Amount</th>
                        <th class="mbui-th">Status</th>
                        <th class="mbui-th">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($pendingOrders as $order)
                        <tr>
                            <td class="mbui-td">
                                <a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a>
                            </td>
                            <td class="mbui-td">{{ $order->itemName() }}</td>
                            <td class="mbui-td">TZS {{ number_format($order->amount) }}
                                <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs font-semibold text-gray-600" />
                            </td>
                            <td class="mbui-td"><x-mbui.status-badge :status="$order->status" /></td>
                            <td class="mbui-td">
                                <a href="{{ route('user.payments.create', $order) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Submit payment</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    @endif

    @if ($subscriptions->isNotEmpty())
        <div class="mt-10">
            <div class="mbui-page-header">
                <h2 class="mbui-section-label">Recent Subscriptions</h2>
                <a href="{{ route('user.subscriptions.index') }}" class="mbui-anchor text-sm">View all</a>
            </div>
            <x-mbui.table class="mt-3">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Product</th>
                        <th class="mbui-th">Plan</th>
                        <th class="mbui-th">Expires</th>
                        <th class="mbui-th">Days left</th>
                        <th class="mbui-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td class="mbui-td font-medium text-gray-900">{{ $subscription->product->name }}</td>
                            <td class="mbui-td">{{ $subscription->plan->name }}</td>
                            <td class="mbui-td">{{ $subscription->expiry_date->format('d M Y') }}</td>
                            <td class="mbui-td">{{ max($subscription->daysRemaining(), 0) }}</td>
                            <td class="mbui-td"><x-mbui.status-badge :status="$subscription->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    @endif

</x-layouts.user>