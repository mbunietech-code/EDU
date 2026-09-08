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

        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">User</th>
                    <th class="mbui-th">Product / Plan</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Payment</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Date</th>
                    <th class="mbui-th">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($orders as $order)
                    <tr>
                        <td class="mbui-td">
                            <a href="{{ route('admin.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a>
                        </td>
                        <td class="mbui-td">{{ $order->user->name }}</td>
                        <td class="mbui-td">{{ $order->itemName() }}{{ $order->plan ? ' / ' . $order->plan->name : '' }}</td>
                        <td class="mbui-td">TZS {{ number_format($order->amount) }}
                            <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td">
                            @if ($order->payment)
                                <x-mbui.status-badge :status="$order->payment->status" />
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$order->status" /></td>
                        <td class="mbui-td text-gray-500">{{ $order->created_at->format('d M Y') }}</td>
                        <td class="mbui-td">
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
                    <tr><td colspan="8" class="mbui-td text-center text-gray-400">No orders found</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>

</x-layouts.admin>