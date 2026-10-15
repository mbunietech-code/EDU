<x-layouts.admin title="Orders" header="Orders">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Orders</h1>
        </div>
    </div>

    @php
        // Every value in the table is a link that narrows the list to it;
        // other active filters are kept and the page resets.
        $by = fn (array $set) => request()->fullUrlWithQuery(array_merge($set, ['page' => null]));
        $without = fn (string ...$keys) => request()->fullUrlWithoutQuery(array_merge($keys, ['page']));
        $sort = request('sort', 'newest');
        $statusLabels = [
            'pending' => 'Pending', 'paid' => 'Paid', 'confirmed' => 'Confirmed (any)', 'active' => 'Active',
            'expired' => 'Expired', 'renewed' => 'Renewed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
        ];
        $productName = $products->firstWhere('id', (int) request('product'))?->name;
        $planName = $plans->firstWhere('id', (int) request('plan'))?->name;
        $chips = array_filter([
            'search' => request('search') ? 'Search: '.request('search') : null,
            'status' => isset($statusLabels[request('status')]) ? 'Status: '.$statusLabels[request('status')] : null,
            'type' => match (request('type')) { 'renewal' => 'Renewals only', 'new' => 'New orders only', default => null },
            'user' => $filterUser ? 'User: '.$filterUser->name : null,
            'product' => request('product') ? 'Product: '.($productName ?? '#'.request('product')) : null,
            'plan' => request('plan') ? 'Plan: '.($planName ?? '#'.request('plan')) : null,
            'device' => request('device') ? 'Device: '.(request('device') === 'none' ? 'None' : request('device')) : null,
        ]);
    @endphp

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-wrap gap-3">
                @foreach (['user', 'sort'] as $keep)
                    @if (request($keep))
                        <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                    @endif
                @endforeach
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Order number, user name or email..."
                    class="mbui-input sm:w-72">
                <select name="status" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach ($statusLabels as $value => $label)
                        @continue(! $renewalsReady && $value === 'renewed')
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($renewalsReady)
                    <select name="type" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Order type">
                        <option value="">New + renewals</option>
                        <option value="new" @selected(request('type') === 'new')>New orders</option>
                        <option value="renewal" @selected(request('type') === 'renewal')>Renewals</option>
                    </select>
                @endif
                <select name="product" class="mbui-input sm:w-48" onchange="if (this.form.plan) this.form.plan.value = ''; this.form.submit()" aria-label="Product">
                    <option value="">All products</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) request('product') === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                @if ($plans->isNotEmpty())
                    <select name="plan" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Plan">
                        <option value="">All plans</option>
                        @foreach ($plans as $pl)
                            <option value="{{ $pl->id }}" @selected((string) request('plan') === (string) $pl->id)>{{ $pl->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="device" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Device">
                    <option value="">All devices</option>
                    <option value="none" @selected(request('device') === 'none')>No device</option>
                    @foreach ($devices as $d)
                        <option value="{{ $d }}" @selected(request('device') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>

            @if ($chips)
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    @foreach ($chips as $key => $label)
                        <a href="{{ $key === 'product' ? $without('product', 'plan') : $without($key) }}"
                           class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 hover:bg-indigo-100"
                           title="Remove this filter">{{ $label }} <span aria-hidden="true">&times;</span></a>
                    @endforeach
                    <a href="{{ route('admin.orders.index') }}" class="font-medium text-gray-500 hover:text-gray-700">Clear all</a>
                    <span class="text-gray-400">&middot; {{ $orders->total() }} {{ \Illuminate\Support\Str::plural('order', $orders->total()) }}</span>
                </div>
            @endif
        </div>

        {{-- Compact so nothing needs a horizontal scrollbar: the date sits under the
             order number, the payment status under the order status, and cells use
             tighter side padding and are allowed to wrap. Click any value to filter
             by it; click Order or Amount to change the sort. --}}
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th px-4">
                        <a href="{{ $by(['sort' => $sort === 'oldest' ? null : 'oldest']) }}" class="inline-flex items-center gap-1 hover:text-gray-900" title="Sort by date">
                            Order <span aria-hidden="true">{{ $sort === 'oldest' ? '↑' : ($sort === 'newest' ? '↓' : '') }}</span>
                        </a>
                    </th>
                    <th class="mbui-th px-3">User</th>
                    <th class="mbui-th px-3">Product / Plan</th>
                    <th class="mbui-th px-3">Device</th>
                    <th class="mbui-th px-3">
                        <a href="{{ $by(['sort' => $sort === 'amount_desc' ? 'amount_asc' : 'amount_desc']) }}" class="inline-flex items-center gap-1 hover:text-gray-900" title="Sort by amount">
                            Amount <span aria-hidden="true">{{ $sort === 'amount_desc' ? '↓' : ($sort === 'amount_asc' ? '↑' : '') }}</span>
                        </a>
                    </th>
                    <th class="mbui-th px-3">Status</th>
                    <th class="mbui-th px-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($orders as $order)
                    <tr>
                        <td class="mbui-td px-4">
                            <x-mbui.order-link :order="$order" :href="route('admin.orders.show', $order)" />
                            @if ($renewalsReady && $order->renews_order_id)
                                <p class="mt-1 text-xs">
                                    <a href="{{ $by(['type' => 'renewal']) }}" class="rounded bg-indigo-50 px-1.5 py-0.5 font-semibold text-indigo-700 hover:bg-indigo-100" title="Show only renewals">Renewal</a>
                                    @if ($order->renewsOrder)
                                        <span class="text-gray-500">of <a href="{{ route('admin.orders.show', $order->renewsOrder) }}" class="mbui-anchor">{{ $order->renewsOrder->order_number }}</a></span>
                                    @endif
                                </p>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal px-3">
                            <a href="{{ $by(['user' => $order->user_id]) }}" class="hover:text-indigo-600 hover:underline" title="Show this user's orders">{{ $order->user->name }}</a>
                        </td>
                        <td class="mbui-td whitespace-normal px-3">
                            @if ($order->product_id)
                                <a href="{{ $by(['product' => $order->product_id, 'plan' => null]) }}" class="font-medium text-gray-900 hover:text-indigo-600 hover:underline" title="Show orders for this product">{{ $order->itemName() }}</a>
                            @else
                                <span class="font-medium text-gray-900">{{ $order->itemName() }}</span>
                            @endif
                            @if ($order->plan)
                                <p class="mt-0.5 text-xs text-gray-500">
                                    <a href="{{ $by(['product' => $order->product_id, 'plan' => $order->plan_id]) }}" class="hover:text-indigo-600 hover:underline" title="Show orders on this plan">{{ $order->plan->name }}</a>
                                </p>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal px-3 text-gray-500">
                            @if ($order->device)
                                <a href="{{ $by(['device' => $order->device]) }}" class="hover:text-indigo-600 hover:underline" title="Show orders for this device">{{ $order->device }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal px-3">
                            <span class="whitespace-nowrap">TZS {{ number_format($order->amount) }}</span>
                            <x-currency-conversion :amount="$order->amount" stacked class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td px-3">
                            <a href="{{ $by(['status' => $order->displayStatus()]) }}" title="Show orders with this status">
                                <x-mbui.status-badge :status="$order->displayStatus()" :label="$order->displayStatusLabel()" />
                            </a>
                            @if ($order->payment)
                                <div class="mt-1.5"><x-mbui.status-badge :status="$order->payment->status" /></div>
                            @endif
                        </td>
                        <td class="mbui-td px-3">
                            <div class="flex items-center gap-2">
                                <x-mbui.icon-link :href="route('admin.orders.edit', $order)" />
                                @if (! $order->isConfirmed() && $order->status !== 'rejected')
                                    <x-mbui.reasoned-action :action="route('admin.orders.reject', $order)" method="POST" label="Disapprove" prompt-text="Why are you disapproving this order?" icon="x-circle" class="rounded p-1.5 text-gray-400 hover:bg-amber-50 hover:text-amber-600" />
                                @endif
                                <x-mbui.reasoned-action :action="route('admin.orders.destroy', $order)" method="DELETE" label="Delete" prompt-text="Why are you deleting this order? This cannot be undone." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">No orders found</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>

</x-layouts.admin>
