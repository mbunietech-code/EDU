<x-layouts.admin title="Research categories" header="Research categories">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('admin.research.index') }}" class="hover:text-gray-700">Research</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">Categories</span>
    </nav>


    <div class="mt-4 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-3">
            @forelse ($categories as $category)
                <x-mbui.card class="p-5">
                    <form method="POST" action="{{ route('admin.research.categories.update', $category) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <div>
                            <label class="mbui-label">Name</label>
                            <input name="name" value="{{ $category->name }}" required class="mbui-input mt-1">
                        </div>
                        <div>
                            <label class="mbui-label">Position</label>
                            <input name="position" type="number" value="{{ $category->position }}" class="mbui-input mt-1">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mbui-label">Description</label>
                            <input name="description" value="{{ $category->description }}" maxlength="500" class="mbui-input mt-1">
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between">
                            <span class="text-xs text-gray-400">{{ $category->researches_count }} {{ Str::plural('paper', $category->researches_count) }} · /research/c/{{ $category->slug }}</span>
                            <div class="flex gap-2">
                                <x-mbui.button type="submit" variant="secondary">Save</x-mbui.button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.research.categories.destroy', $category) }}" class="mt-2"
                          onsubmit="return confirm('Delete this category? Its research becomes uncategorised.')">
                        @csrf @method('DELETE')
                        <button class="text-xs font-medium text-red-600 hover:text-red-800">Delete category</button>
                    </form>
                </x-mbui.card>
            @empty
                <x-mbui.card><x-mbui.empty-state title="No categories yet" message="Add one on the right." /></x-mbui.card>
            @endforelse
        </div>

        <div>
            <x-mbui.card class="p-5">
                <h2 class="mbui-section-label">Add category</h2>
                <form method="POST" action="{{ route('admin.research.categories.store') }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label class="mbui-label">Name</label>
                        <input name="name" required class="mbui-input mt-1" placeholder="e.g. Research Methodology">
                    </div>
                    <div>
                        <label class="mbui-label">Description</label>
                        <textarea name="description" rows="2" maxlength="500" class="mbui-input mt-1"></textarea>
                    </div>
                    <div>
                        <label class="mbui-label">Position</label>
                        <input name="position" type="number" value="0" class="mbui-input mt-1">
                    </div>
                    <x-mbui.button type="submit" class="w-full">Add category</x-mbui.button>
                </form>
            </x-mbui.card>
        </div>
    </div>
</x-layouts.admin>
