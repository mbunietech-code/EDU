<x-layouts.app :title="$category->name" header="Learning">

    <nav class="mb-4 flex items-center gap-1.5 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('learn.categories.index') }}" class="mbui-anchor">Categories</a>
        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
        <span class="truncate text-gray-700" aria-current="page">{{ $category->name }}</span>
    </nav>

    <div class="mbui-page-header">
        <div class="min-w-0">
            <h1 class="mbui-title">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-1 text-sm text-gray-500">{{ $category->description }}</p>
            @endif
            <p class="mt-2 flex flex-wrap gap-x-3 text-xs font-medium text-gray-500">
                <span>{{ $courseTotal }} {{ Str::plural('course', $courseTotal) }}</span>
                <span>{{ $videos->total() }} {{ Str::plural('lesson', $videos->total()) }}</span>
            </p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.videos.index', ['category' => $category->slug])" variant="secondary">Filter lessons</x-mbui.btn-link>
        </div>
    </div>

    {{-- Courses --}}
    @if ($courses->isNotEmpty())
        <section class="mt-6" aria-labelledby="category-courses">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="category-courses" class="mbui-section-label">Courses</h2>
                @if ($courseTotal > $courses->count())
                    <a href="{{ route('learn.courses.index', ['category' => $category->slug]) }}" class="mbui-anchor text-sm">
                        View all {{ $courseTotal }} courses
                    </a>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($courses as $course)
                    <x-learning.course-card :course="$course" :progress="$courseProgress[$course->id] ?? null" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Lessons --}}
    <section class="mt-8" aria-labelledby="category-lessons">
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 id="category-lessons" class="mbui-section-label">Video lessons</h2>
            @if ($videos->total() > 0)
                <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-sm" role="group" aria-label="Sort lessons">
                    @foreach (['latest' => 'Latest', 'popular' => 'Most viewed'] as $key => $label)
                        <a href="{{ route('learn.categories.show', ['category' => $category, 'sort' => $key]) }}"
                            @class([
                                'rounded-md px-3 py-1.5 font-medium transition',
                                'bg-white text-gray-900 shadow-sm' => $sort === $key,
                                'text-gray-600 hover:text-gray-900' => $sort !== $key,
                            ])
                            @if ($sort === $key) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($videos->isEmpty())
            <x-learning.empty title="No videos available in this category."
                message="New lessons will appear here as soon as they are published."
                :action-href="route('learn.videos.index')" action-label="Browse all lessons" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($videos as $video)
                    <x-learning.video-card :video="$video" />
                @endforeach
            </div>
            <div class="mt-6">{{ $videos->links() }}</div>
        @endif
    </section>
</x-layouts.app>
