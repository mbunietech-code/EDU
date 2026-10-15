<x-layouts.admin title="Subscriptions" header="Subscriptions">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Subscriptions</h1>
        </div>
    </div>

    @php
        // Every value in the table is a link that narrows the list to it;
        // other active filters are kept and the page resets.
        $by = fn (array $set) => request()->fullUrlWithQuery(array_merge($set, ['page' => null]));
        $without = fn (string ...$keys) => request()->fullUrlWithoutQuery(array_merge($keys, ['page']));
        $sort = request('sort');
        $statuses = ['pending', 'active', 'expiring_soon', 'expired', 'suspended', 'revoked', 'cancelled'];
        $windows = ['past' => 'Date passed', '7d' => 'Next 7 days', '30d' => 'Next 30 days', 'later' => 'After 30 days'];
        $expires = (string) request('expires');
        $expiresLabel = $windows[$expires] ?? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) ? rescue(fn () => \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $expires)->format('d M Y'), $expires, false) : null);
        $productName = $products->firstWhere('id', (int) request('product'))?->name;
        $planName = $plans->firstWhere('id', (int) request('plan'))?->name;
        $chips = array_filter([
            'search' => request('search') ? 'Search: '.request('search') : null,
            'status' => in_array(request('status'), $statuses, true) ? 'Status: '.ucwords(str_replace('_', ' ', request('status'))) : null,
            'user' => $filterUser ? 'User: '.$filterUser->name : null,
            'product' => request('product') ? 'Product: '.($productName ?? '#'.request('product')) : null,
            'plan' => request('plan') ? 'Plan: '.($planName ?? '#'.request('plan')) : null,
            'device' => request('device') ? 'Device: '.(request('device') === 'none' ? 'None' : request('device')) : null,
            'account' => request('account') ? 'Account: '.(request('account') === 'none' ? 'None' : '#'.request('account')) : null,
            'expires' => $expiresLabel ? 'Expires: '.$expiresLabel : null,
        ]);
    @endphp

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-wrap gap-3">
                @foreach (['user', 'account', 'sort'] as $keep)
                    @if (request($keep))
                        <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
                    @endif
                @endforeach
                @if ($expires !== '' && ! isset($windows[$expires]))
                    <input type="hidden" name="expires" value="{{ $expires }}">
                @endif
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by user name or email..."
                    class="mbui-input sm:w-72">
                <select name="status" class="mbui-input sm:w-44" onchange="this.form.submit()" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
                <select name="product" class="mbui-input sm:w-48" onchange="if (this.form.plan) this.form.plan.value = ''; this.form.submit()" aria-label="Product">
                    <option value="">All products</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) request('product') === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                @if ($plans->isNotEmpty())
                    <select name="plan" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Plan">
                        <option value="">All plans</option>
                        @foreach ($plans as $pl)
                            <option value="{{ $pl->id }}" @selected((string) request('plan') === (string) $pl->id)>{{ $pl->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="device" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Device">
                    <option value="">All devices</option>
                    <option value="none" @selected(request('device') === 'none')>No device</option>
                    @foreach ($devices as $d)
                        <option value="{{ $d }}" @selected(request('device') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
                @if (isset($windows[$expires]) || $expires === '')
                    <select name="expires" class="mbui-input sm:w-40" onchange="this.form.submit()" aria-label="Expires">
                        <option value="">Any expiry</option>
                        @foreach ($windows as $value => $label)
                            <option value="{{ $value }}" @selected($expires === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>

            @if ($chips)
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    @foreach ($chips as $key => $label)
                        <a href="{{ $key === 'product' ? $without('product', 'plan') : $without($key) }}"
                           class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 hover:bg-indigo-100"
                           title="Remove this filter">{{ $label }} <span aria-hidden="true">&times;</span></a>
                    @endforeach
                    <a href="{{ route('admin.subscriptions.index') }}" class="font-medium text-gray-500 hover:text-gray-700">Clear all</a>
                    <span class="text-gray-400">&middot; {{ $subscriptions->total() }} {{ \Illuminate\Support\Str::plural('subscription', $subscriptions->total()) }}</span>
                </div>
            @endif
        </div>

        {{-- Tighter side padding, and user / product / device may wrap onto a second
             line, so all columns fit without a horizontal scrollbar. Click any value
             to filter by it; click Expires to sort by end date. --}}
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th px-4">User</th>
                    <th class="mbui-th px-3">Product / Plan</th>
                    <th class="mbui-th px-3">Device</th>
                    <th class="mbui-th px-3">Account</th>
                    <th class="mbui-th px-3">
                        <a href="{{ $by(['sort' => $sort === 'expires_asc' ? 'expires_desc' : 'expires_asc']) }}" class="inline-flex items-center gap-1 hover:text-gray-900" title="Sort by expiry date">
                            Expires <span aria-hidden="true">{{ $sort === 'expires_asc' ? '↑' : ($sort === 'expires_desc' ? '↓' : '') }}</span>
                        </a>
                    </th>
                    <th class="mbui-th px-3">Status</th>
                    <th class="mbui-th px-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="mbui-td whitespace-normal px-4 font-medium text-gray-900">
                            <a href="{{ $by(['user' => $subscription->user_id]) }}" class="hover:text-indigo-600 hover:underline" title="Show this user's subscriptions">{{ $subscription->user->name }}</a>
                        </td>
                        <td class="mbui-td whitespace-normal px-3">
                            <a href="{{ $by(['product' => $subscription->product_id, 'plan' => null]) }}" class="font-medium text-gray-900 hover:text-indigo-600 hover:underline" title="Show subscriptions for this product">{{ $subscription->product->name }}</a>
                            @if ($subscription->plan)
                                <p class="mt-0.5 text-xs text-gray-500">
                                    <a href="{{ $by(['product' => $subscription->product_id, 'plan' => $subscription->plan_id]) }}" class="hover:text-indigo-600 hover:underline" title="Show subscriptions on this plan">{{ $subscription->plan->name }}</a>
                                </p>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal px-3 text-gray-500">
                            @if ($subscription->order?->device)
                                <a href="{{ $by(['device' => $subscription->order->device]) }}" class="hover:text-indigo-600 hover:underline" title="Show subscriptions for this device">{{ $subscription->order->device }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="mbui-td px-3">
                            @if ($subscription->account_id)
                                <a href="{{ $by(['account' => $subscription->account_id]) }}" class="hover:text-indigo-600 hover:underline" title="Show subscriptions on this account">#{{ $subscription->account_id }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="mbui-td px-3">
                            <a href="{{ $by(['expires' => $subscription->expiry_date->toDateString()]) }}" class="hover:text-indigo-600 hover:underline" title="Show subscriptions ending this day">{{ $subscription->expiry_date->format('d M Y') }}</a>
                        </td>
                        <td class="mbui-td px-3">
                            <a href="{{ $by(['status' => $subscription->status]) }}" title="Show subscriptions with this status">
                                <x-mbui.status-badge :status="$subscription->status" />
                            </a>
                        </td>
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
