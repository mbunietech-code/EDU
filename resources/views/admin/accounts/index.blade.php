<x-layouts.admin title="Accounts" header="Accounts">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Accounts</h1>
        </div>
        <a href="{{ route('admin.accounts.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add account</a>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
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
                        <td class="mbui-td"><x-mbui.status-badge :status="$account->status" /></td>
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
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No accounts</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $accounts->links() }}</div>

</x-layouts.admin>