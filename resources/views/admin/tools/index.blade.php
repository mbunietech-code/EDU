<x-layouts.admin title="Research Tools" header="Research Tools">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Research Tools</h1>
            <p class="mt-1 text-sm text-gray-500">Programs and apps sold with a product key, separate from Products.</p>
        </div>
        <a href="{{ route('admin.tools.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add tool</a>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Slug</th>
                    <th class="mbui-th">Orders</th>
                    <th class="mbui-th">Price</th>
                    <th class="mbui-th">Featured</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($tools as $tool)
                    <tr>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                @if ($tool->imageUrl())
                                    <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}" class="h-10 w-10 rounded-lg border border-gray-200 object-cover">
                                @endif
                                <span class="font-medium text-gray-900">{{ $tool->name }}</span>
                            </div>
                        </td>
                        <td class="mbui-td text-gray-500">{{ $tool->slug }}</td>
                        <td class="mbui-td">{{ $tool->orders_count }}</td>
                        <td class="mbui-td">TZS {{ number_format($tool->price) }}
                            <x-currency-conversion :amount="$tool->price" class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td">
                            @if ($tool->is_featured)
                                <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$tool->status" /></td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <x-mbui.icon-link :href="route('admin.tools.edit', $tool)" />
                                <x-mbui.reasoned-action :action="route('admin.tools.destroy', $tool)" method="DELETE" label="Delete" prompt-text="Why are you deleting {{ $tool->name }}? This permanently removes the tool." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">No tools yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $tools->links() }}</div>

</x-layouts.admin>
