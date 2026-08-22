<x-layouts.admin title="Edit Scholarship" header="Edit Scholarship">

    <div class="max-w-2xl">
        <form method="POST" action="{{ route('admin.scholarships.update', $scholarship) }}" enctype="multipart/form-data" class="mbui-card p-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <x-input-label for="title" value="Title" />
                <x-text-input id="title" class="mbui-input mt-1" type="text" name="title" :value="old('title', $scholarship->title)" required />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="slug" value="Slug" />
                <x-text-input id="slug" class="mbui-input mt-1" type="text" name="slug" :value="old('slug', $scholarship->slug)" required />
                <x-input-error :messages="$errors->get('slug')" class="mt-2" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="country" value="Country" />
                    <x-text-input id="country" class="mbui-input mt-1" type="text" name="country" :value="old('country', $scholarship->country)" placeholder="e.g. United Kingdom" />
                    <x-input-error :messages="$errors->get('country')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="deadline" value="Application deadline" />
                    <x-text-input id="deadline" class="mbui-input mt-1" type="date" name="deadline" :value="old('deadline', $scholarship->deadline?->format('Y-m-d'))" />
                    <x-input-error :messages="$errors->get('deadline')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="5" class="mbui-input mt-1">{{ old('description', $scholarship->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="apply_url" value="Apply link" />
                <x-text-input id="apply_url" class="mbui-input mt-1" type="url" name="apply_url" :value="old('apply_url', $scholarship->apply_url)" placeholder="https://..." />
                <x-input-error :messages="$errors->get('apply_url')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">Where applicants go to apply. Opens in a new tab.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mbui-input mt-1">
                        @foreach (['draft', 'published', 'archived'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $scholarship->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sort_order" value="Sort order" />
                    <x-text-input id="sort_order" class="mbui-input mt-1" type="number" name="sort_order" :value="old('sort_order', $scholarship->sort_order)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="image" value="Image" />
                    @if ($scholarship->imageUrl())
                        <img src="{{ $scholarship->imageUrl() }}" alt="{{ $scholarship->title }}" class="mt-1 mb-2 h-16 w-16 rounded-lg border border-gray-200 object-cover">
                    @endif
                    <input id="image" type="file" name="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $scholarship->is_featured)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Featured on homepage
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.scholarships.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Update scholarship</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
