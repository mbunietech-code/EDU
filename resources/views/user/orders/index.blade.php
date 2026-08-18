<x-layouts.user title="My Orders" header="My Orders">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">My Orders</h1>
            <p class="mt-1 text-sm text-gray-500">Track your orders and their payment status.</p>
        </div>
        <a href="{{ route('public.products.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">New order</a>
    </div>

    <div class="mt-6">
        @if ($orders->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No orders yet" message="Browse AI tools to create your first order." />
            </x-mbui.card>
        @else
            <x-mbui.table>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Order</th>
                        <th class="mbui-th">Product</th>
                        <th class="mbui-th">Plan</th>
                        <th class="mbui-th">Amount</th>
                        <th class="mbui-th">Paid</th>
                        <th class="mbui-th">Status</th>
                        <th class="mbui-th">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($orders as $order)
                        <tr>
                            <td class="mbui-td">
                                <a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a>
                                <p class="text-xs text-gray-400">{{ $order->created_at->format('d M Y H:i') }}</p>
                            </td>
                            <td class="mbui-td">{{ $order->product->name }}</td>
                            <td class="mbui-td">{{ $order->plan->name }}</td>
                            <td class="mbui-td font-medium text-gray-900">TZS {{ number_format($order->amount) }}
                                <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs text-gray-400" />
                            </td>
                            <td class="mbui-td">
                                @if ($order->payment)
                                    <x-mbui.status-badge :status="$order->payment->status" />
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="mbui-td"><x-mbui.status-badge :status="$order->status" /></td>
                            <td class="mbui-td">
                                @if ($order->isPending())
                                    <a href="{{ route('user.payments.create', $order) }}" class="mbui-anchor text-sm">Submit payment</a>
                                @else
                                    <a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor text-sm">View</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>

</x-layouts.user>