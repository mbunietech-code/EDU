<x-layouts.admin title="Account" header="Account details">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $account->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $account->product->name }}</p>
        </div>
        <x-mbui.status-badge :status="$account->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @if (session()->has('decrypted_credentials'))
            <x-mbui.card>
                <h2 class="mbui-section-label">Decrypted credentials</h2>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-emerald-300 whitespace-pre-wrap">{{ session('decrypted_credentials') }}</pre>
            </x-mbui.card>
        @else
            @if ($account->credentials)
                <x-mbui.card>
                    <h2 class="mbui-section-label">Credentials</h2>
                    <p class="mt-3 text-sm text-gray-500">Stored encrypted. You may decrypt to view — this action is audited.</p>
                    <form method="POST" action="{{ route('admin.accounts.decrypt', $account) }}" class="mt-4">
                        @csrf
                        <x-mbui.button type="submit" variant="secondary">Decrypt credentials</x-mbui.button>
                    </form>
                </x-mbui.card>
            @endif
        @endif

        <x-mbui.card>
            <h2 class="mbui-section-label">Details</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Description</dt>
                    <dd class="font-medium text-gray-900">{{ $account->description ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Status</dt>
                    <dd><x-mbui.status-badge :status="$account->status" /></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Created</dt>
                    <dd class="font-medium text-gray-900">{{ $account->created_at->format('d M Y') }}</dd>
                </div>
            </dl>
        </x-mbui.card>

        <x-mbui.table :title="'Linked subscriptions (' . $account->subscriptions->count() . ')'" class="lg:col-span-2">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">User</th>
                    <th class="mbui-th">Expiry</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($account->subscriptions as $subscription)
                    <tr>
                        <td class="mbui-td">{{ $subscription->user->name }}</td>
                        <td class="mbui-td">{{ $subscription->expiry_date->format('d M Y') }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$subscription->status" /></td>
                        <td class="mbui-td">
                            <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="mbui-anchor text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No subscriptions linked</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>