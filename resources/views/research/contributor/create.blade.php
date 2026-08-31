<x-layouts.user title="New research" header="New research">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('research.contributor.index') }}" class="hover:text-gray-700">My Research</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">New</span>
    </nav>

    <x-mbui.card class="mt-4 max-w-2xl">
        <form method="POST" action="{{ route('research.contributor.store') }}" class="space-y-5">
            @csrf
            <div>
                <label class="mbui-label">Title</label>
                <input name="title" value="{{ old('title') }}" required maxlength="255" class="mbui-input mt-1" placeholder="e.g. Adoption of Mobile Money Among SMEs in Dodoma">
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>
            <div>
                <label class="mbui-label">Area</label>
                <select name="research_category_id" class="mbui-input mt-1">
                    <option value="">— choose an area —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('research_category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mbui-label">Summary <span class="text-gray-400">(optional)</span></label>
                <textarea name="summary" rows="3" maxlength="1000" class="mbui-input mt-1" placeholder="A short abstract shown in the library listing.">{{ old('summary') }}</textarea>
            </div>
            <div class="flex justify-end gap-2">
                <x-mbui.btn-link :href="route('research.contributor.index')" variant="secondary">Cancel</x-mbui.btn-link>
                <x-mbui.button type="submit">Create &amp; add chapters</x-mbui.button>
            </div>
        </form>
    </x-mbui.card>
</x-layouts.user>
