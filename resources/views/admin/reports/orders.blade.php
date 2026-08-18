<x-layouts.admin title="Orders Report" header="Orders Report">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Orders</h1>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-500">Months:</label>
            <select name="months" onchange="this.form.submit()" class="mbui-input sm:w-24">
                @foreach ([3, 6, 12, 24] as $m)
                    <option value="{{ $m }}" @selected($months === $m)>{{ $m }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Year</th>
                    <th class="mbui-th">Month</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Orders</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($report as $row)
                    <tr>
                        <td class="mbui-td">{{ $row['year'] }}</td>
                        <td class="mbui-td">{{ Carbon\Carbon::create()->month($row['month'])->format('F') }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$row['status']" /></td>
                        <td class="mbui-td font-semibold text-gray-900">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No orders in this range</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>