<x-layouts.user title="Payments" header="Payments">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">My Payments</h1>
            <p class="mt-1 text-sm text-gray-500">Your payment history and verification status.</p>
        </div>
    </div>

    <div class="mt-6">
        @if ($payments->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No payments yet" message="Create an order and submit a payment to get started." />
            </x-mbui.card>
        @else
            <x-mbui.table>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Order</th>
                        <th class="mbui-th">Method</th>
                        <th class="mbui-th">Reference</th>
                        <th class="mbui-th">Amount</th>
                        <th class="mbui-th">Status</th>
                        <th class="mbui-th">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($payments as $payment)
                        <tr>
                            <td class="mbui-td">
                                <a href="{{ route('user.payments.show', $payment) }}" class="mbui-anchor">{{ $payment->order->order_number }}</a>
                            </td>
                            <td class="mbui-td">{{ $payment->paymentMethodLabel() }}</td>
                            <td class="mbui-td">{{ $payment->transaction_reference }}</td>
                            <td class="mbui-td font-medium text-gray-900">TZS {{ number_format($payment->amount) }}</td>
                            <td class="mbui-td"><x-mbui.status-badge :status="$payment->status" /></td>
                            <td class="mbui-td text-gray-500">{{ $payment->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
            <div class="mt-6">{{ $payments->links() }}</div>
        @endif
    </div>

</x-layouts.user>