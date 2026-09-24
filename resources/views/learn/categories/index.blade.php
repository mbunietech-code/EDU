<x-layouts.app title="Learning categories" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Categories</h1>
            <p class="mt-1 text-sm text-gray-500">Browse courses and video lessons by subject.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.courses.index')" variant="secondary">All courses</x-mbui.btn-link>
            <x-mbui.btn-link :href="route('learn.videos.index')">Video lessons</x-mbui.btn-link>
        </div>
    </div>

    <div class="mt-6">
        @if ($categories->isEmpty())
            <x-learning.empty title="No categories yet"
                message="Learning categories appear here once an administrator creates them." />
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    @php($empty = $category->courses_count === 0 && $category->videos_count === 0)
                    <a href="{{ route('learn.categories.show', $category) }}"
                        class="mbui-card group flex items-start gap-3 p-4 transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $empty ? 'bg-gray-100 text-gray-400' : 'bg-indigo-50 text-indigo-600' }}" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $category->name }}</h2>
                            @if ($category->description)
                                <p class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ $category->description }}</p>
                            @endif
                            <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium text-gray-500">
                                <span>{{ $category->courses_count }} {{ Str::plural('course', $category->courses_count) }}</span>
                                <span>{{ $category->videos_count }} {{ Str::plural('lesson', $category->videos_count) }}</span>
                            </p>
                        </div>
                        <svg class="mt-1 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $categories->links() }}</div>
        @endif
    </div>
</x-layouts.app>
