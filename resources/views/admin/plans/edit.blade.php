<x-layouts.admin title="Edit Plan" header="Edit Plan">

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="mbui-card p-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <x-input-label for="product_id" value="Product" />
                <select id="product_id" name="product_id" class="mbui-input mt-1" required>
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id', $plan->product_id) == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name', $plan->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2" class="mbui-input mt-1">{{ old('description', $plan->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="duration_days" value="Duration (days)" />
                    <x-text-input id="duration_days" class="mbui-input mt-1" type="number" min="1" name="duration_days" :value="old('duration_days', $plan->duration_days)" required />
                    <x-input-error :messages="$errors->get('duration_days')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="price" value="Price (TZS)" />
                    <x-text-input id="price" class="mbui-input mt-1" type="number" step="0.01" min="0" name="price" :value="old('price', $plan->price)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mbui-input mt-1">
                        <option value="active" @selected(old('status', $plan->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sort_order" value="Sort order" />
                    <x-text-input id="sort_order" class="mbui-input mt-1" type="number" min="0" name="sort_order" :value="old('sort_order', $plan->sort_order)" />
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.plans.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Update plan</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>