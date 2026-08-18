<x-layouts.admin title="Payments" header="Payments">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payments</h1>
            <p class="mt-1 text-sm text-gray-500">
                <span class="font-semibold text-amber-600">{{ $pendingCount }}</span> payment(s) pending review
            </p>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET">
                <select name="status" onchange="this.form.submit()" class="mbui-input sm:w-40">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'approved', 'rejected'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <noscript><x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button></noscript>
            </form>
        </div>

        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">User</th>
                    <th class="mbui-th">Method</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Submitted</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($payments as $payment)
                    <tr class="{{ $payment->isPending() ? 'bg-amber-50/40' : '' }}">
                        <td class="mbui-td"><a href="{{ route('admin.orders.show', $payment->order) }}" class="mbui-anchor">{{ $payment->order->order_number }}</a></td>
                        <td class="mbui-td">{{ $payment->user->name }}</td>
                        <td class="mbui-td">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                        <td class="mbui-td font-medium text-gray-900">TZS {{ number_format($payment->amount) }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$payment->status" /></td>
                        <td class="mbui-td text-gray-500">{{ $payment->created_at->format('d M Y H:i') }}</td>
                        <td class="mbui-td">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="mbui-anchor text-sm">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">No payments found</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $payments->links() }}</div>

</x-layouts.admin>