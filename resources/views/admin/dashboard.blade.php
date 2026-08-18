<x-layouts.admin title="Dashboard" header="Dashboard">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Operations Overview</h1>
            <p class="mt-1 text-sm text-gray-500">Real-time health of the MbunieEduHub access platform.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-mbui.stats-card title="Total Users" :value="number_format($metrics['total_users'])" />
        <x-mbui.stats-card title="Total Products" :value="number_format($metrics['total_products'])" />
        <x-mbui.stats-card title="Active Subscriptions" :value="number_format($metrics['active_subscriptions'])" />
        <x-mbui.stats-card title="Pending Payments" :value="number_format($metrics['pending_payments'])" />
        <x-mbui.stats-card title="Accounts (Avail / Total)" :value="$metrics['available_accounts'] . ' / ' . $metrics['total_accounts']" :trend="$metrics['assigned_accounts'] . ' assigned'" />
        <x-mbui.stats-card title="Expiring Soon" :value="number_format($metrics['expiring_soon'])" :trend="'Threshold: 3 days'" />
        <x-mbui.stats-card title="Expired" :value="number_format($metrics['expired_subscriptions'])" />
        <x-mbui.stats-card title="Monthly Revenue" :value="'TZS ' . number_format($metrics['monthly_revenue'])"
            :trend="'&asymp; $' . number_format($metrics['monthly_revenue'] * $rates['USD'], 2) . ' USD &middot; &asymp; &yen;' . number_format($metrics['monthly_revenue'] * $rates['CNY'], 2) . ' CNY'" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <x-mbui.table :title="'Recent Orders'">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">User</th>
                    <th class="mbui-th">Product</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($recentOrders as $order)
                    <tr>
                        <td class="mbui-td"><a href="{{ route('admin.orders.show', $order) }}" class="mbui-anchor">{{ $order->order_number }}</a></td>
                        <td class="mbui-td">{{ $order->user->name }}</td>
                        <td class="mbui-td">{{ $order->product->name }}</td>
                        <td class="mbui-td">TZS {{ number_format($order->amount) }}
                            <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs text-gray-400" />
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$order->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No orders yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>

        <x-mbui.table :title="'Recent Payments'">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">User</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($recentPayments as $payment)
                    <tr>
                        <td class="mbui-td"><a href="{{ route('admin.payments.show', $payment) }}" class="mbui-anchor">{{ $payment->order->order_number }}</a></td>
                        <td class="mbui-td">{{ $payment->user->name }}</td>
                        <td class="mbui-td">TZS {{ number_format($payment->amount) }}
                            <x-currency-conversion :amount="$payment->amount" class="mt-1 text-xs text-gray-400" />
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$payment->status" /></td>
                        <td class="mbui-td">
                            @if ($payment->isPending())
                                <a href="{{ route('admin.payments.show', $payment) }}" class="mbui-anchor text-sm">Review</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No payments yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    @if (count($revenueReport) > 0)
        <div class="mt-8">
            <x-mbui.card>
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Revenue (last 6 months)</h2>
                    <a href="{{ route('admin.reports.revenue') }}" class="mbui-anchor text-sm">Full report</a>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach (collect($revenueReport)->sortByDesc('month')->take(6) as $row)
                        <div class="rounded-lg bg-gradient-to-br from-indigo-50 to-purple-50 p-4 text-center">
                            <p class="text-xs font-medium text-gray-500">{{ Carbon\Carbon::create()->month($row['month'])->format('M') }}</p>
                            <p class="mt-1 text-sm font-bold text-gray-900">TZS {{ number_format((float) $row['total']) }}</p>
                            <x-currency-conversion :amount="$row['total']" class="mt-1 text-xs text-gray-400" />
                        </div>
                    @endforeach
                </div>
            </x-mbui.card>
        </div>
    @endif

</x-layouts.admin>