<x-layouts.admin title="Activity Logs" header="Activity Logs">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Activity Logs</h1>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="action" value="{{ request('action') }}" placeholder="Filter by action (e.g. payment_approved)" class="mbui-input sm:w-80">
                <input type="text" name="entity" value="{{ request('entity') }}" placeholder="Entity (e.g. Payment)" class="mbui-input sm:w-56">
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>
        </div>

        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">When</th>
                    <th class="mbui-th">Actor</th>
                    <th class="mbui-th">Action</th>
                    <th class="mbui-th">Entity</th>
                    <th class="mbui-th">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($logs as $log)
                    <tr>
                        <td class="mbui-td text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="mbui-td">{{ $log->actor?->name ?? 'System' }}</td>
                        <td class="mbui-td">
                            <x-mbui.badge appearance="neutral">{{ $log->action }}</x-mbui.badge>
                        </td>
                        <td class="mbui-td">{{ $log->entity ? ($log->entity . ' #' . $log->entity_id) : '-' }}</td>
                        <td class="mbui-td text-gray-500">{{ $log->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No activity logged</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>

</x-layouts.admin>