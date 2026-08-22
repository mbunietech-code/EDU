<x-layouts.admin title="Scholarships" header="Scholarships">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Scholarships</h1>
            <p class="mt-1 text-sm text-gray-500">Free scholarship listings shown on the homepage and user sidebar.</p>
        </div>
        <a href="{{ route('admin.scholarships.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add scholarship</a>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Title</th>
                    <th class="mbui-th">Country</th>
                    <th class="mbui-th">Deadline</th>
                    <th class="mbui-th">Featured</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($scholarships as $scholarship)
                    <tr>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                @if ($scholarship->imageUrl())
                                    <img src="{{ $scholarship->imageUrl() }}" alt="{{ $scholarship->title }}" class="h-10 w-10 rounded-lg border border-gray-200 object-cover">
                                @endif
                                <span class="font-medium text-gray-900">{{ $scholarship->title }}</span>
                            </div>
                        </td>
                        <td class="mbui-td text-gray-500">{{ $scholarship->country ?? '—' }}</td>
                        <td class="mbui-td">
                            {{ $scholarship->deadline?->format('d M Y') ?? '—' }}
                            @if ($scholarship->isExpired())
                                <x-mbui.badge appearance="danger">Expired</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td">
                            @if ($scholarship->is_featured)
                                <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$scholarship->status" /></td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.scholarships.edit', $scholarship) }}" class="mbui-anchor text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.scholarships.destroy', $scholarship) }}" onsubmit="return confirm('Delete {{ $scholarship->title }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No scholarships yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $scholarships->links() }}</div>

</x-layouts.admin>
