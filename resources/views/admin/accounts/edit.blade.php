<x-layouts.admin title="Edit Account" header="Edit Account">

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="mbui-card p-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <x-input-label for="product_id" value="Product" />
                <select id="product_id" name="product_id" class="mbui-input mt-1" required>
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id', $account->product_id) == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name', $account->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2" class="mbui-input mt-1">{{ old('description', $account->description) }}</textarea>
            </div>
            <div>
                <x-input-label for="credentials" value="Credentials (leave empty to keep current)" />
                <textarea id="credentials" name="credentials" rows="3" class="mbui-input mt-1 font-mono text-xs">{{ old('credentials') }}</textarea>
                <x-input-error :messages="$errors->get('credentials')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">Stored encrypted. Existing credentials are kept if this field is left empty.</p>
            </div>
            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mbui-input mt-1">
                    @foreach (['available', 'assigned', 'suspended', 'expired', 'maintenance', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $account->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.accounts.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Update account</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>