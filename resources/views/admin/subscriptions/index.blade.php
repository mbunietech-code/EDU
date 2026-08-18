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

        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">User</th>
                    <th class="mbui-th">Product / Plan</th>
                    <th class="mbui-th">Account</th>
                    <th class="mbui-th">Expires</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $subscription->user->name }}</td>
                        <td class="mbui-td">{{ $subscription->product->name }} / {{ $subscription->plan->name }}</td>
                        <td class="mbui-td">{{ $subscription->account_id ? '#' . $subscription->account_id : '-' }}</td>
                        <td class="mbui-td">{{ $subscription->expiry_date->format('d M Y') }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$subscription->status" /></td>
                        <td class="mbui-td">
                            <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="mbui-anchor text-sm">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No subscriptions found</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $subscriptions->links() }}</div>

</x-layouts.admin>