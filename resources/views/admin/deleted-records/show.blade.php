<x-layouts.admin title="Deleted Item" header="Deleted Items">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $deletedRecord->label }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $deletedRecord->entity }} &middot; deleted {{ $deletedRecord->created_at->format('d M Y H:i') }}</p>
        </div>
        <a href="{{ route('admin.deleted-records.index') }}" class="mbui-anchor text-sm">Back to Deleted Items</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">Reason given</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $deletedRecord->reason }}</p>
            </x-mbui.card>

            <x-mbui.card>
                <h2 class="mbui-section-label">Snapshot before deletion</h2>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-700">{{ json_encode($deletedRecord->snapshot, JSON_PRETTY_PRINT) }}</pre>
            </x-mbui.card>
        </div>

        <div>
            <x-mbui.card class="p-6">
                <h2 class="mbui-section-label">Deleted by</h2>
                <p class="mt-2 text-sm font-medium text-gray-900">{{ $deletedRecord->deleter?->name ?? 'System' }}</p>
                <p class="text-xs text-gray-500">{{ $deletedRecord->deleter?->email }}</p>
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-xs text-gray-500">Original ID: {{ $deletedRecord->entity_id ?? '—' }}</p>
                </div>
            </x-mbui.card>
        </div>
    </div>

</x-layouts.admin>
