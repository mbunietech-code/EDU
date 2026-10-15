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
                    <dt class="text-gray-500">Our plan ends</dt>
                    <dd class="font-medium {{ $account->planState() === 'expired' ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $account->expires_at ? $account->expires_at->format('d M Y') : '—' }}
                        @if ($account->planState() === 'expired')
                            <span class="block text-xs font-normal">Ended: not given to new customers</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Created</dt>
                    <dd class="font-medium text-gray-900">{{ $account->created_at->format('d M Y') }}</dd>
                </div>
            </dl>
        </x-mbui.card>

        {{-- One customer uses an account at a time; when they leave, it goes to
             the next one. So the holder now is shown apart from the history. --}}
        @php
            $current = $account->currentSubscription();
            $previous = $account->subscriptions->reject(fn ($s) => $current && $s->id === $current->id)->sortByDesc('id');
        @endphp

        <x-mbui.card class="lg:col-span-2">
            <h2 class="mbui-section-label">Current user</h2>
            @if ($current)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm">
                        <p class="font-semibold text-gray-900">{{ $current->user->name }} <span class="font-normal text-gray-500">({{ $current->user->email }})</span></p>
                        <p class="mt-0.5 text-gray-500">Until {{ $current->expiry_date->format('d M Y') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-mbui.status-badge :status="$current->status" />
                        <a href="{{ route('admin.subscriptions.show', $current) }}" class="mbui-anchor text-sm">View subscription</a>
                    </div>
                </div>
                @if ($previous->isNotEmpty())
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        This account was used by {{ $previous->count() }} {{ \Illuminate\Support\Str::plural('customer', $previous->count()) }} before.
                        Change its password if it was not changed when it moved to {{ $current->user->name }}, so earlier customers can no longer sign in.
                    </p>
                @endif
            @else
                <p class="mt-3 text-sm text-gray-500">Nobody is using this account now.</p>
            @endif
        </x-mbui.card>

        @if ($previous->isNotEmpty())
            <x-mbui.table :title="'Previous users (' . $previous->count() . ')'" class="lg:col-span-2">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="mbui-th">User</th>
                        <th class="mbui-th">Ended</th>
                        <th class="mbui-th">Status</th>
                        <th class="mbui-th">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($previous as $subscription)
                        <tr>
                            <td class="mbui-td">{{ $subscription->user->name }}</td>
                            <td class="mbui-td">{{ $subscription->expiry_date->format('d M Y') }}</td>
                            <td class="mbui-td"><x-mbui.status-badge :status="$subscription->status" /></td>
                            <td class="mbui-td">
                                <a href="{{ route('admin.subscriptions.show', $subscription) }}" class="mbui-anchor text-sm">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        @endif
    </div>

</x-layouts.admin>