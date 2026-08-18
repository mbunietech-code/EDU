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
            <div>
                <x-input-label value="Duration type" />
                <div class="mt-1 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4">
                        <input type="radio" name="duration_type" value="days" class="mt-1 h-4 w-4 text-indigo-600" @checked(old('duration_type', $plan->duration_type) === 'days')>
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Duration in days</span>
                            <span class="block text-xs text-gray-500">Plan expires after a fixed number of days</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4">
                        <input type="radio" name="duration_type" value="lifetime" class="mt-1 h-4 w-4 text-indigo-600" @checked(old('duration_type', $plan->duration_type) === 'lifetime')>
                        <span>
                            <span class="block text-sm font-medium text-gray-900">Single purchase / Lifetime</span>
                            <span class="block text-xs text-gray-500">No expiry — permanent access</span>
                        </span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('duration_type')" class="mt-2" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div id="daysField">
                    <x-input-label for="duration_days" value="Duration (days)" />
                    <x-text-input id="duration_days" class="mbui-input mt-1" type="number" min="1" name="duration_days" :value="old('duration_days', $plan->duration_days)" />
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

    <script>
        const daysField = document.getElementById('daysField');
        const durationRadios = document.querySelectorAll('input[name="duration_type"]');

        function toggleDaysField() {
            const selected = document.querySelector('input[name="duration_type"]:checked').value;
            const daysInput = daysField.querySelector('input');
            daysField.style.display = selected === 'lifetime' ? 'none' : '';
            daysInput.required = selected === 'days';
        }

        durationRadios.forEach((radio) => radio.addEventListener('change', toggleDaysField));
        toggleDaysField();
    </script>

</x-layouts.admin>