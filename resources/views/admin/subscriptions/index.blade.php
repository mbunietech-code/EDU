<x-layouts.admin title="Subscriptions" header="Subscriptions">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Subscriptions</h1>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by user name or email..."
                    class="mbui-input sm:w-80">
                <select name="status" class="mbui-input sm:w-44">
                    <option value="">All statuses</option>
                    @foreach (['pending', 'active', 'expiring_soon', 'expired', 'suspended', 'revoked', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>
        </div>

        {{-- Tighter side padding, and user / product / device may wrap onto a second
             line, so all columns fit without a horizontal scrollbar. --}}
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th px-4">User</th>
                    <th class="mbui-th px-3">Product / Plan</th>
                    <th class="mbui-th px-3">Device</th>
                    <th class="mbui-th px-3">Account</th>
                    <th class="mbui-th px-3">Expires</th>
                    <th class="mbui-th px-3">Status</th>
                    <th class="mbui-th px-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="mbui-td whitespace-normal px-4 font-medium text-gray-900">{{ $subscription->user->name }}</td>
                        <td class="mbui-td whitespace-normal px-3">
                            <span class="font-medium text-gray-900">{{ $subscription->product->name }}</span>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $subscription->plan->name }}</p>
                        </td>
                        <td class="mbui-td whitespace-normal px-3 text-gray-500">{{ $subscription->order?->device ?: '—' }}</td>
                        <td class="mbui-td px-3">{{ $subscription->account_id ? '#' . $subscription->account_id : '-' }}</td>
                        <td class="mbui-td px-3">{{ $subscription->expiry_date->format('d M Y') }}</td>
                        <td class="mbui-td px-3"><x-mbui.status-badge :status="$subscription->status" /></td>
                        <td class="mbui-td px-3">
                            <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="mbui-anchor text-sm">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">No subscriptions found</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $subscriptions->links() }}</div>

</x-layouts.admin>