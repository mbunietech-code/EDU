<x-layouts.app title="Courses" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Courses</h1>
            <p class="mt-1 text-sm text-gray-500">Structured learning paths with video lessons and live sessions.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.categories.index')" variant="secondary">Categories</x-mbui.btn-link>
            <x-mbui.btn-link :href="route('learn.videos.index')">Video lessons</x-mbui.btn-link>
        </div>
    </div>

    {{-- Category chips --}}
    @if ($categories->isNotEmpty())
        <div class="mt-6 overflow-x-auto">
            <div class="flex gap-2 pb-1 sm:flex-wrap" role="list" aria-label="Filter by category">
                @php($chipBase = request()->except(['category', 'page']))
                <a href="{{ route('learn.courses.index', $chipBase) }}" role="listitem"
                    @class([
                        'shrink-0 rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                        'bg-indigo-600 text-white ring-indigo-600' => ! $filters['category'],
                        'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => $filters['category'],
                    ])
                    @if (! $filters['category']) aria-current="true" @endif>All</a>
                @foreach ($categories as $cat)
                    @php($active = $activeCategory?->id === $cat->id)
                    <a href="{{ route('learn.courses.index', array_merge($chipBase, ['category' => $cat->slug])) }}" role="listitem"
                        @class([
                            'shrink-0 rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                            'bg-indigo-600 text-white ring-indigo-600' => $active,
                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => ! $active,
                        ])
                        @if ($active) aria-current="true" @endif>{{ $cat->name }}</a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('learn.courses.index') }}" class="mbui-card mt-4 p-4">
        @if ($filters['category'])
            <input type="hidden" name="category" value="{{ $filters['category'] }}">
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(2,minmax(0,1fr))_auto] lg:items-end">
            <div>
                <label for="course-q" class="mbui-label">Search</label>
                <input id="course-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100"
                    placeholder="Course title or summary" class="mbui-input mt-1 w-full">
            </div>
            <div>
                <label for="course-level" class="mbui-label">Level</label>
                <select id="course-level" name="level" class="mbui-input mt-1 w-full">
                    <option value="">All levels</option>
                    @foreach ($levels as $key => $label)
                        <option value="{{ $key }}" @selected($filters['level'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="course-sort" class="mbui-label">Sort by</label>
                <select id="course-sort" name="sort" class="mbui-input mt-1 w-full">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-1">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="mine" value="1" @checked($filters['mine'])
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                    My courses
                </label>
                <x-mbui.button type="submit">Apply</x-mbui.button>
                @if ($filters['q'] !== '' || $filters['level'] || $filters['mine'] || $filters['category'] || $filters['sort'] !== 'latest')
                    <a href="{{ route('learn.courses.index') }}" class="mbui-anchor text-sm">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <div class="mt-6">
        @if ($courses->isEmpty())
            @if ($filters['mine'])
                <x-learning.empty title="You have no courses yet"
                    message="Courses you enrol in or start watching will appear here."
                    :action-href="route('learn.courses.index')" action-label="Browse all courses" />
            @elseif ($filters['q'] !== '' || $filters['level'] || $filters['category'])
                <x-learning.empty title="No courses match your filters"
                    message="Try a different search term, level or category."
                    :action-href="route('learn.courses.index')" action-label="Clear filters" />
            @else
                <x-learning.empty title="No courses available yet"
                    message="Published courses will appear here. Meanwhile, browse the individual video lessons."
                    :action-href="route('learn.videos.index')" action-label="Browse lessons" />
            @endif
        @else
            <p class="mb-3 text-sm text-gray-500">
                {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}
                @if ($activeCategory) in <span class="font-medium text-gray-700">{{ $activeCategory->name }}</span>@endif
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($courses as $course)
                    <x-learning.course-card :course="$course" :progress="$courseProgress[$course->id] ?? null" />
                @endforeach
            </div>
            <div class="mt-6">{{ $courses->links() }}</div>
        @endif
    </div>
</x-layouts.app>
