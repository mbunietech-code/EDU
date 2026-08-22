<x-layouts.admin title="Plans" header="Plans">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Plans</h1>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add plan</a>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Product</th>
                    <th class="mbui-th">Duration</th>
                    <th class="mbui-th">Price</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($plans as $plan)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $plan->name }}</td>
                        <td class="mbui-td">{{ $plan->product->name }}</td>
                        <td class="mbui-td">{{ $plan->durationLabel() }}</td>
                        <td class="mbui-td">TZS {{ number_format($plan->price) }}
                            <x-currency-conversion :amount="$plan->price" class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$plan->status" /></td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.plans.edit', $plan) }}" class="mbui-anchor text-sm">Edit</a>
                                @if ($plan->status === 'active')
                                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan permanently? This also removes any linked orders.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No plans</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $plans->links() }}</div>

</x-layouts.admin>