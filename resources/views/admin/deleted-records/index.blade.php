<x-layouts.admin title="Deleted Items" header="Deleted Items">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Deleted Items</h1>
            <p class="mt-1 text-sm text-gray-500">Everything deleted anywhere in the admin panel, with who deleted it and why.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex gap-3">
                <select name="entity" class="mbui-input sm:w-56">
                    <option value="">All types</option>
                    @foreach ($entities as $e)
                        <option value="{{ $e }}" @selected($entity === $e)>{{ $e }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Type</th>
                    <th class="mbui-th">Item</th>
                    <th class="mbui-th">Reason</th>
                    <th class="mbui-th">Deleted by</th>
                    <th class="mbui-th">When</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($records as $record)
                    <tr>
                        <td class="mbui-td"><x-mbui.badge appearance="neutral">{{ $record->entity }}</x-mbui.badge></td>
                        <td class="mbui-td font-medium text-gray-900">{{ $record->label }}</td>
                        <td class="mbui-td max-w-xs truncate" title="{{ $record->reason }}">{{ $record->reason }}</td>
                        <td class="mbui-td text-gray-500">{{ $record->deleter?->name ?? 'System' }}</td>
                        <td class="mbui-td text-gray-500">{{ $record->created_at->format('d M Y H:i') }}</td>
                        <td class="mbui-td">
                            <a href="{{ route('admin.deleted-records.show', $record) }}" class="mbui-anchor text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">Nothing has been deleted yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $records->links() }}</div>

</x-layouts.admin>
