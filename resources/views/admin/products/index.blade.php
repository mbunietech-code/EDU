<x-layouts.admin title="Products" header="Products">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Products</h1>
        </div>
        <a href="{{ route('admin.products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add product</a>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Name</th>
                    <th class="mbui-th">Slug</th>
                    <th class="mbui-th">Plans</th>
                    <th class="mbui-th">Accounts</th>
                    <th class="mbui-th">Price</th>
                    <th class="mbui-th">Featured</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Type</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($products as $product)
                    <tr>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                @if ($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg border border-gray-200 object-cover">
                                @endif
                                <span class="font-medium text-gray-900">{{ $product->name }}</span>
                            </div>
                        </td>
                        <td class="mbui-td text-gray-500">{{ $product->slug }}</td>
                        <td class="mbui-td">{{ $product->plans_count }}</td>
                        <td class="mbui-td">{{ $product->accounts_count }}</td>
                        <td class="mbui-td">TZS {{ number_format($product->price) }}
                            <x-currency-conversion :amount="$product->price" class="mt-1 text-xs text-gray-400" />
                        </td>
                        <td class="mbui-td">
                            @if ($product->is_featured)
                                <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$product->status" /></td>
                        <td class="mbui-td">
                            @if ($product->isSoftware())
                                <x-mbui.badge appearance="info">Software</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="neutral">Subscription</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.products.edit', $product) }}" class="mbui-anchor text-sm">Edit</a>
                                @if ($product->isSoftware())
                                    <a href="{{ route('admin.products.keys', $product) }}" class="mbui-anchor text-sm">Keys</a>
                                @endif
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete {{ $product->name }}? This permanently removes the product and its related plans, accounts and orders.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="mbui-td text-center text-gray-400">No products</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $products->links() }}</div>

</x-layouts.admin>