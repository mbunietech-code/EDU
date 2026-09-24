<x-layouts.app title="Video lessons" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Video lessons</h1>
            <p class="mt-1 text-sm text-gray-500">Watch at your own pace — your progress is saved automatically.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.courses.index')" variant="secondary">Courses</x-mbui.btn-link>
        </div>
    </div>

    {{-- Category chips --}}
    @if ($categories->isNotEmpty())
        <div class="mt-6 overflow-x-auto">
            @php($chipBase = request()->except(['category', 'course', 'page']))
            <div class="flex gap-2 pb-1 sm:flex-wrap" role="list" aria-label="Filter by category">
                <a href="{{ route('learn.videos.index', $chipBase) }}" role="listitem"
                    @class([
                        'shrink-0 rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                        'bg-indigo-600 text-white ring-indigo-600' => ! $filters['category'],
                        'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => $filters['category'],
                    ])
                    @if (! $filters['category']) aria-current="true" @endif>All</a>
                @foreach ($categories as $cat)
                    @php($active = $activeCategory?->id === $cat->id)
                    <a href="{{ route('learn.videos.index', array_merge($chipBase, ['category' => $cat->slug])) }}" role="listitem"
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
    <form method="GET" action="{{ route('learn.videos.index') }}" class="mbui-card mt-4 p-4">
        @if ($filters['category'])
            <input type="hidden" name="category" value="{{ $filters['category'] }}">
        @endif
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            <div class="sm:col-span-2">
                <label for="video-q" class="mbui-label">Search</label>
                <input id="video-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100"
                    placeholder="Lesson title or description" class="mbui-input mt-1 w-full">
            </div>
            <div>
                <label for="video-course" class="mbui-label">Course</label>
                <select id="video-course" name="course" class="mbui-input mt-1 w-full">
                    <option value="">All courses</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->slug }}" @selected($activeCourse?->id === $c->id)>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="video-duration" class="mbui-label">Length</label>
                <select id="video-duration" name="duration" class="mbui-input mt-1 w-full">
                    <option value="">Any length</option>
                    @foreach ($durations as $key => $label)
                        <option value="{{ $key }}" @selected($filters['duration'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="video-status" class="mbui-label">My progress</label>
                <select id="video-status" name="status" class="mbui-input mt-1 w-full">
                    <option value="">Any</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="video-sort" class="mbui-label">Sort by</label>
                <select id="video-sort" name="sort" class="mbui-input mt-1 w-full">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <x-mbui.button type="submit">Apply filters</x-mbui.button>
            @if ($filtered || $filters['sort'] !== 'latest')
                <a href="{{ route('learn.videos.index') }}" class="mbui-anchor text-sm">Reset</a>
            @endif
        </div>
    </form>

    <div class="mt-6">
        @if ($videos->isEmpty())
            @if ($filters['status'] === 'completed')
                <x-learning.empty title="No completed lessons yet"
                    message="Lessons you finish will show up here."
                    :action-href="route('learn.videos.index')" action-label="Browse all lessons" />
            @elseif ($filters['status'] === 'in_progress')
                <x-learning.empty title="Nothing in progress"
                    message="Start a lesson and it will appear here until you finish it."
                    :action-href="route('learn.videos.index')" action-label="Browse all lessons" />
            @elseif ($filters['status'] === 'not_started')
                <x-learning.empty title="You have started every lesson here"
                    message="Great progress! Try another category or check your lessons in progress."
                    :action-href="route('learn.videos.index', ['status' => 'in_progress'])" action-label="Lessons in progress" />
            @elseif ($filtered)
                <x-learning.empty title="No lessons match your filters"
                    message="Try a different search term, course, length or category."
                    :action-href="route('learn.videos.index')" action-label="Clear filters" />
            @else
                <x-learning.empty title="No video lessons yet"
                    message="Published lessons will appear here." />
            @endif
        @else
            <p class="mb-3 text-sm text-gray-500">
                {{ $videos->total() }} {{ Str::plural('lesson', $videos->total()) }}
                @if ($activeCategory) in <span class="font-medium text-gray-700">{{ $activeCategory->name }}</span>@endif
                @if ($activeCourse) · <span class="font-medium text-gray-700">{{ $activeCourse->title }}</span>@endif
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($videos as $video)
                    <x-learning.video-card :video="$video" />
                @endforeach
            </div>
            <div class="mt-6">{{ $videos->links() }}</div>
        @endif
    </div>
</x-layouts.app>
