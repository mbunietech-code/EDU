<x-layouts.admin title="Learning categories" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Categories</h1>
            <p class="mt-1 text-sm text-gray-500">Group courses, lessons and live rooms by subject. Learners browse the library by these categories.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('admin.learning.courses.index')" variant="secondary">Courses</x-mbui.btn-link>
        </div>
    </div>

    @include('admin.learning.partials.catalog-tabs', ['active' => 'categories'])

    @if ($canManage)
        <x-mbui.card class="mt-6" x-data="{ open: {{ $errors->any() && old('_form') === 'create' ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">New category</h2>
                    <p class="text-sm text-gray-500">Name it after a subject area, e.g. “Research Methods”.</p>
                </div>
                <x-mbui.button variant="secondary" @click="open = !open" x-bind:aria-expanded="open.toString()" aria-controls="new-category-form">
                    <span x-text="open ? 'Close' : 'Add category'">Add category</span>
                </x-mbui.button>
            </div>
            <form id="new-category-form" x-show="open" x-cloak method="POST" action="{{ route('admin.learning.categories.store') }}"
                class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-6">
                @csrf
                <input type="hidden" name="_form" value="create">
                <div class="md:col-span-3">
                    <label for="new-name" class="mbui-label">Name <span class="text-red-600">*</span></label>
                    <input id="new-name" name="name" type="text" required maxlength="255" value="{{ old('_form') === 'create' ? old('name') : '' }}" class="mbui-input mt-1 w-full">
                </div>
                <div class="md:col-span-2">
                    <label for="new-icon" class="mbui-label">Icon <span class="font-normal text-gray-400">(optional)</span></label>
                    <input id="new-icon" name="icon" type="text" maxlength="60" value="{{ old('_form') === 'create' ? old('icon') : '' }}" placeholder="e.g. academic-cap" class="mbui-input mt-1 w-full">
                </div>
                <div>
                    <label for="new-position" class="mbui-label">Position</label>
                    <input id="new-position" name="position" type="number" min="0" max="65535" value="{{ old('_form') === 'create' ? old('position') : '' }}" placeholder="Auto" class="mbui-input mt-1 w-full">
                </div>
                <div class="md:col-span-6">
                    <label for="new-description" class="mbui-label">Description <span class="font-normal text-gray-400">(optional, 500 characters)</span></label>
                    <textarea id="new-description" name="description" rows="2" maxlength="500" class="mbui-input mt-1 w-full">{{ old('_form') === 'create' ? old('description') : '' }}</textarea>
                </div>
                <div class="md:col-span-6 flex justify-end">
                    <x-mbui.button type="submit">Create category</x-mbui.button>
                </div>
            </form>
        </x-mbui.card>
    @endif

    @if ($categories->isEmpty())
        <x-learning.empty class="mt-6" title="No categories yet"
            :message="$canManage ? 'Create the first category above, then add courses to it.' : 'Categories created by content managers will be listed here.'" />
    @else
        <x-mbui.card class="mt-6 overflow-hidden p-0">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                <div class="col-span-5">Category</div>
                <div class="col-span-2 text-right">Courses</div>
                <div class="col-span-2 text-right">Lessons</div>
                <div class="col-span-1 text-right">Position</div>
                <div class="col-span-2 text-right"><span class="sr-only">Actions</span></div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($categories as $category)
                    <li x-data="{ editing: {{ $errors->any() && (int) old('_category') === $category->id ? 'true' : 'false' }} }" class="px-4 py-4 md:px-6">
                        <div x-show="!editing" class="grid grid-cols-2 gap-x-4 gap-y-2 md:grid-cols-12 md:items-center">
                            <div class="col-span-2 min-w-0 md:col-span-5">
                                <p class="font-medium text-gray-900">
                                    {{ $category->name }}
                                    @if ($category->icon)
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-500">{{ $category->icon }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">/{{ $category->slug }}@if ($category->description) · {{ Str::limit($category->description, 120) }}@endif</p>
                            </div>
                            <div class="text-sm text-gray-700 md:col-span-2 md:text-right">
                                <span class="text-xs text-gray-500 md:hidden">Courses: </span>
                                <a href="{{ route('admin.learning.courses.index', ['category' => $category->id]) }}" class="mbui-anchor">{{ number_format($category->courses_count) }}</a>
                            </div>
                            <div class="text-sm text-gray-700 md:col-span-2 md:text-right">
                                <span class="text-xs text-gray-500 md:hidden">Lessons: </span>{{ number_format($category->videos_count) }}
                            </div>
                            <div class="text-sm text-gray-700 md:col-span-1 md:text-right">
                                <span class="text-xs text-gray-500 md:hidden">Position: </span>{{ $category->position }}
                            </div>
                            <div class="flex items-center justify-end gap-1 md:col-span-2">
                                <a href="{{ route('learn.categories.show', $category->slug) }}" class="rounded p-1.5 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600" title="View as learner">
                                    <span class="sr-only">View {{ $category->name }} as a learner</span>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </a>
                                @if ($canManage)
                                    <button type="button" @click="editing = true" class="rounded p-1.5 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600" title="Edit">
                                        <span class="sr-only">Edit {{ $category->name }}</span>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                    </button>
                                    <x-learning.confirm-delete :action="route('admin.learning.categories.destroy', $category)"
                                        :title="'Delete the category “'.$category->name.'”?'"
                                        :impact="$impact[$category->id] ?? []"
                                        :button-label="'Delete '.$category->name">
                                        @if ($category->courses_count || $category->videos_count)
                                            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">This category still has courses or lessons, so it cannot be deleted until they are moved or deleted.</p>
                                        @endif
                                    </x-learning.confirm-delete>
                                @endif
                            </div>
                        </div>

                        @if ($canManage)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('admin.learning.categories.update', $category) }}"
                                class="grid grid-cols-1 gap-3 md:grid-cols-6">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_category" value="{{ $category->id }}">
                                @php($useOld = (int) old('_category') === $category->id)
                                <div class="md:col-span-3">
                                    <label for="cat-name-{{ $category->id }}" class="mbui-label">Name</label>
                                    <input id="cat-name-{{ $category->id }}" name="name" type="text" required maxlength="255" value="{{ $useOld ? old('name') : $category->name }}" class="mbui-input mt-1 w-full">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="cat-icon-{{ $category->id }}" class="mbui-label">Icon</label>
                                    <input id="cat-icon-{{ $category->id }}" name="icon" type="text" maxlength="60" value="{{ $useOld ? old('icon') : $category->icon }}" class="mbui-input mt-1 w-full">
                                </div>
                                <div>
                                    <label for="cat-pos-{{ $category->id }}" class="mbui-label">Position</label>
                                    <input id="cat-pos-{{ $category->id }}" name="position" type="number" min="0" max="65535" value="{{ $useOld ? old('position') : $category->position }}" class="mbui-input mt-1 w-full">
                                </div>
                                <div class="md:col-span-6">
                                    <label for="cat-desc-{{ $category->id }}" class="mbui-label">Description</label>
                                    <textarea id="cat-desc-{{ $category->id }}" name="description" rows="2" maxlength="500" class="mbui-input mt-1 w-full">{{ $useOld ? old('description') : $category->description }}</textarea>
                                </div>
                                <div class="flex justify-end gap-2 md:col-span-6">
                                    <x-mbui.button variant="secondary" @click="editing = false">Cancel</x-mbui.button>
                                    <x-mbui.button type="submit">Save</x-mbui.button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-mbui.card>

        <div class="mt-4">{{ $categories->links() }}</div>
    @endif
</x-layouts.admin>
