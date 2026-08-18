<x-layouts.admin :title="'Product Keys - ' . $product->name" header="Product Keys">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $product->keys_available_count }} available &middot; {{ $product->keys_sold_count }} sold
                &middot; {{ $product->software_filename ? 'File: '.$product->software_filename : 'No software file uploaded' }}
            </p>
        </div>
        <a href="{{ route('admin.products.edit', $product) }}" class="mbui-anchor text-sm">Back to product</a>
    </div>

    <div class="mt-6 mbui-card p-6">
        <h2 class="text-base font-semibold text-gray-900">Add product keys</h2>
        <p class="mt-1 text-sm text-gray-500">Paste one key per line. Keys are assigned automatically to orders when their payment is approved.</p>

        <form method="POST" action="{{ route('admin.products.keys.store', $product) }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <x-input-label for="keys_list" value="Keys (one per line)" />
                <textarea id="keys_list" name="keys_list" rows="5" class="mbui-input mt-1" placeholder="ABC-1234-DEFG&#10;HIJ-5678-KLMN&#10;..." required>{{ old('keys_list') }}</textarea>
                <x-input-error :messages="$errors->get('keys_list')" class="mt-2" />
            </div>
            <div class="flex justify-end">
                <x-mbui.button type="submit">Add keys</x-mbui.button>
            </div>
        </form>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Key</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Buyer</th>
                    <th class="mbui-th">Sold at</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($keys as $key)
                    <tr>
                        <td class="mbui-td font-mono text-sm text-gray-900">{{ $key->key_value }}</td>
                        <td class="mbui-td"><x-mbui.status-badge :status="$key->status" /></td>
                        <td class="mbui-td">
                            @if ($key->order)
                                <a href="{{ route('admin.orders.show', $key->order) }}" class="mbui-anchor text-sm">{{ $key->order->user->name }}</a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td text-gray-500">{{ $key->sold_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="mbui-td">
                            @if ($key->isAvailable())
                                <form method="POST" action="{{ route('admin.product-keys.destroy', $key) }}" onsubmit="return confirm('Delete this product key?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            @else
                                <span class="text-gray-300 text-sm">Sold</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No product keys yet. Add some above.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $keys->links() }}</div>

</x-layouts.admin>