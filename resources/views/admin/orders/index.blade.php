<x-layouts.admin title="Orders" header="Orders">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Orders</h1>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Order number, user name or email..."
                    class="mbui-input sm:w-80">
                <select name="status" class="mbui-input sm:w-40">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'paid', 'confirmed', 'rejected', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>
        </div>

        {{-- Compact so nothing needs a horizontal scrollbar: the date sits under the
             order number, the payment status under the order status, and cells use
             tighter side padding and are allowed to wrap. --}}
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th px-4">Order</th>
                    <th class="mbui-th px-3">User</th>
                    <th class="mbui-th px-3">Product / Plan</th>
                    <th class="mbui-th px-3">Device</th>
                    <th class="mbui-th px-3">Amount</th>
                    <th class="mbui-th px-3">Status</th>
                    <th class="mbui-th px-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($orders as $order)
                    <tr>
                        <td class="mbui-td px-4">
                            <a href="{{ route('admin.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a>
                            <p class="mt-0.5 text-xs text-gray-400">{{ $order->created_at->format('d M Y') }}</p>
                        </td>
                        <td class="mbui-td whitespace-normal px-3">{{ $order->user->name }}</td>
                        <td class="mbui-td whitespace-normal px-3">
                            <span class="font-medium text-gray-900">{{ $order->itemName() }}</span>
                            @if ($order->plan)
                                <p class="mt-0.5 text-xs text-gray-500">{{ $order->plan->name }}</p>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal px-3 text-gray-500">{{ $order->device ?: '—' }}</td>
                        <td class="mbui-td whitespace-normal px-3">
                            <span class="whitespace-nowrap">TZS {{ number_format($order->amount) }}</span>
                            <x-currency-conversion :amount="$order->amount" stacked class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td px-3">
                            <x-mbui.status-badge :status="$order->status" />
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