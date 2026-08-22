<x-layouts.admin title="User" header="User details">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $user->email }} &middot; Joined {{ $user->created_at->format('d M Y') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <x-mbui.status-badge :status="$user->status" />
            @if (! $user->is_admin)
                @if ($user->status === 'suspended')
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                        @csrf
                        <x-mbui.button type="submit" variant="success">Activate</x-mbui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                        @csrf
                        <x-mbui.button type="submit" variant="danger">Suspend</x-mbui.button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-mbui.table :title="'Orders (' . $user->orders->count() . ')'">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">Product</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($user->orders as $order)
                    <tr>
                        <td class="mbui-td"><a href="{{ route('admin.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a></td>
                        <td class="mbui-td">{{ $order->itemName() }}</td>
                        <td class="mbui-td">TZS {{ number_format($order->amount) }}
                            <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$order->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No orders</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>

        <x-mbui.table :title="'Subscriptions (' . $user->subscriptions->count() . ')'">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Product</th>
                    <th class="mbui-th">Plan</th>
                    <th class="mbui-th">Expires</th>
                    <th class="mbui-th">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($user->subscriptions as $subscription)
                    <tr>
                        <td class="mbui-td"><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="mbui-anchor">{{ $subscription->product->name }}</a></td>
                        <td class="mbui-td">{{ $subscription->plan->name }}</td>
                        <td class="mbui-td">{{ $subscription->expiry_date->format('d M Y') }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$subscription->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No subscriptions</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">
        <x-mbui.table :title="'Payments (' . $user->payments->count() . ')'">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">Method</th>
                    <th class="mbui-th">Reference</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($user->payments as $payment)
                    <tr>
                        <td class="mbui-td"><a href="{{ route('admin.payments.show', $payment) }}" class="mbui-anchor">{{ $payment->order->order_number }}</a></td>
                        <td class="mbui-td">{{ $payment->paymentMethodLabel() }}</td>
                        <td class="mbui-td">{{ $payment->transaction_reference }}</td>
                        <td class="mbui-td">TZS {{ number_format($payment->amount) }}
                            <x-currency-conversion :amount="$payment->amount" class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$payment->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No payments</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>