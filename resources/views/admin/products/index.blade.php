<x-layouts.admin title="Products" header="Products">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Products</h1>
        </div>
        <a href="{{ route('admin.products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add product</a>
    </div>

    {{-- Compact on purpose: slug and "Featured" sit under the name and cells use
         tighter side padding, so every column fits without a horizontal scrollbar. --}}
    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th px-4">Product</th>
                    <th class="mbui-th px-3">Plans</th>
                    <th class="mbui-th px-3">Accounts</th>
                    <th class="mbui-th px-3">Price</th>
                    <th class="mbui-th px-3">Status</th>
                    <th class="mbui-th px-3">Type</th>
                    <th class="mbui-th px-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($products as $product)
                    <tr>
                        <td class="mbui-td px-4">
                            <div class="flex items-center gap-3">
                                @if ($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-10 w-10 shrink-0 rounded-lg border border-gray-200 object-cover">
                                @endif
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ $product->name }}</span>
                                        @if ($product->is_featured)
                                            <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-400">{{ $product->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="mbui-td px-3">{{ $product->plans_count }}</td>
                        <td class="mbui-td px-3">{{ $product->accounts_count }}</td>
                        <td class="mbui-td whitespace-normal px-3">
                            <span class="whitespace-nowrap">TZS {{ number_format($product->price) }}</span>
                            <x-currency-conversion :amount="$product->price" stacked class="mt-1 text-xs font-semibold text-gray-600" />
                        </td>
                        <td class="mbui-td px-3"><x-mbui.status-badge :status="$product->status" /></td>
                        <td class="mbui-td px-3">
                            @if ($product->isSoftware())
                                <x-mbui.badge appearance="info">Software</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="neutral">Subscription</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td px-3">
                            <div class="flex items-center gap-3">
                                <x-mbui.icon-link :href="route('admin.products.edit', $product)" />
                                <x-mbui.reasoned-action :action="route('admin.products.destroy', $product)" method="DELETE" label="Delete" prompt-text="Why are you deleting {{ $product->name }}? This permanently removes the product and its related plans, accounts and orders." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">No products</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $products->links() }}</div>

</x-layouts.admin>
