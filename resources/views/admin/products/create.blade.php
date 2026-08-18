<x-layouts.admin title="Add Product" header="Add Product">

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="mbui-card p-6 space-y-5">
            @csrf
            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="slug" value="Slug" />
                <x-text-input id="slug" class="mbui-input mt-1" type="text" name="slug" :value="old('slug')" placeholder="ai-tool-name" required />
                <x-input-error :messages="$errors->get('slug')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="4" class="mbui-input mt-1">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="features" value="Features (one per line)" />
                <textarea id="features" name="features_list" rows="4" class="mbui-input mt-1" placeholder="Feature one&#10;Feature two">{{ old('features_list') }}</textarea>
                <x-input-error :messages="$errors->get('features')" class="mt-2" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="price" value="Base price (TZS)" />
                    <x-text-input id="price" class="mbui-input mt-1" type="number" step="0.01" min="0" name="price" :value="old('price', 0)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mbui-input mt-1">
                        @foreach (['draft', 'published', 'archived'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="type" value="Product type" />
                    <select id="type" name="type" class="mbui-input mt-1">
                        <option value="subscription" @selected(old('type', 'subscription') === 'subscription')>Subscription (account access)</option>
                        <option value="software" @selected(old('type') === 'software')>Software (product key + download)</option>
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="software_version" value="Software version (optional)" />
                    <x-text-input id="software_version" class="mbui-input mt-1" type="text" name="software_version" :value="old('software_version')" placeholder="v1.0" />
                    <x-input-error :messages="$errors->get('software_version')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="software_file" value="Software file (exe / zip)" />
                    <input id="software_file" type="file" name="software_file" accept=".exe,.zip,.msi,.rar,.apk"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <x-input-error :messages="$errors->get('software_file')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-400">Used only for software products. Buyers can download it within the 20-minute access window after their payment is approved.</p>
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="image" value="Image" />
                    <input id="image" type="file" name="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Featured product
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.products.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Create product</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>