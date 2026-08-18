<x-layouts.admin title="Products Report" header="Products Report">

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Orders</th>
                    <th class="mbui-th">Subscriptions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($report as $row)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $row['name'] }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$row['status']" /></td>
                        <td class="mbui-td">{{ $row['orders_count'] }}</td>
                        <td class="mbui-td">{{ $row['subscriptions_count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No products</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>