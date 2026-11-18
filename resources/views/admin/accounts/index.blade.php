<x-layouts.admin title="Accounts" header="Accounts">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Accounts</h1>
        </div>
        <a href="{{ route('admin.accounts.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add account</a>
    </div>

    @if ($awaiting->isNotEmpty())
        <div class="mt-6 mbui-card overflow-hidden border-amber-200">
            <div class="flex items-center gap-2 border-b border-amber-100 bg-amber-50 px-4 py-3">
                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-sm font-semibold text-amber-800">Waiting for login details ({{ $awaiting->count() }})</h2>
                <p class="hidden text-xs text-amber-700 sm:block">Paid customers who got a new account because the stock was empty. Add the login and they are notified.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($awaiting as $waiting)
                    @php $holder = $waiting->currentSubscription(); @endphp
                    <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $waiting->product?->name }} · {{ $holder?->user?->name ?? 'Customer' }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $holder?->user?->email }} · paid {{ $waiting->created_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('admin.accounts.edit', $waiting) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Add login</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Account, or customer name / email..."
                    class="mbui-input sm:w-72">
                <select name="status" class="mbui-input sm:w-40">
                    <option value="">All statuses</option>
                    @foreach (['available', 'assigned', 'suspended', 'expired', 'maintenance', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="product_id" class="mbui-input sm:w-52">
                    <option value="">All products</option>
                    @foreach (\App\Models\Product::all() as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
            </form>
        </div>

        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Product</th>
                    <th class="mbui-th">Current user</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($accounts as $account)
                    <tr>
                        <td class="mbui-td">
                            <a href="{{ route('admin.accounts.show', $account) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $account->name }}</a>
                        </td>
                        <td class="mbui-td">{{ $account->product->name }}</td>
                        <td class="mbui-td whitespace-normal">
                            @if ($holder = $account->currentSubscription())
                                <span class="font-medium text-gray-900">{{ $holder->user->name }}</span>
                                <p class="text-xs text-gray-500">until {{ $holder->expiry_date->format('d M Y') }}</p>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td">
                            <x-mbui.status-badge :status="$account->status" />
                            @if ($account->planState() === 'expired')
                                <p class="mt-1 text-xs text-red-600">Our plan ended {{ $account->expires_at->format('d M Y') }}</p>
                            @endif
                        </td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <x-mbui.icon-link :href="route('admin.accounts.edit', $account)" />
                                @if ($account->status !== 'archived')
                                    <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Archive</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No accounts</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $accounts->links() }}</div>

</x-layouts.admin>