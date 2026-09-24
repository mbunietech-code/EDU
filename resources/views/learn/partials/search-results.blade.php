{{-- Renders one result type. Vars: $type (videos|courses|categories|topics|rooms|instructors), $items (iterable) --}}
@switch($type)
    @case('videos')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $video)
                <x-learning.video-card :video="$video" />
            @endforeach
        </div>
        @break

    @case('courses')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $course)
                <x-learning.course-card :course="$course" />
            @endforeach
        </div>
        @break

    @case('rooms')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $room)
                <x-learning.room-card :room="$room" />
            @endforeach
        </div>
        @break

    @case('categories')
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $category)
                <li>
                    <a href="{{ route('learn.categories.show', $category) }}" class="mbui-card flex h-full flex-col p-4 transition hover:ring-2 hover:ring-indigo-500/40">
                        <span class="text-sm font-semibold text-gray-900">{{ $category->name }}</span>
                        @if ($category->description)
                            <span class="mt-1 line-clamp-2 text-xs text-gray-600">{{ $category->description }}</span>
                        @endif
                        <span class="mt-auto pt-3 text-xs text-gray-500">
                            {{ $category->courses_count }} {{ Str::plural('course', $category->courses_count) }} ·
                            {{ $category->videos_count }} {{ Str::plural('lesson', $category->videos_count) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        @break

    @case('topics')
        <ul class="mbui-card divide-y divide-gray-100">
            @foreach ($items as $topic)
                <li>
                    <a href="{{ route('learn.courses.show', $topic->course) }}" class="flex flex-col gap-1 px-4 py-3 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-900">{{ $topic->title }}</span>
                            <span class="block truncate text-xs text-gray-500">
                                {{ $topic->course->title }}@if ($topic->course->category) · {{ $topic->course->category->name }}@endif
                            </span>
                        </span>
                        <span class="shrink-0 text-xs text-gray-500">{{ $topic->videos_count }} {{ Str::plural('lesson', $topic->videos_count) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        @break

    @case('instructors')
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $instructor)
                <li>
                    <a href="{{ route('learn.instructors.show', $instructor) }}" class="mbui-card flex items-center gap-3 p-4 transition hover:ring-2 hover:ring-indigo-500/40">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700" aria-hidden="true">
                            {{ Str::upper(Str::substr($instructor->name, 0, 1)) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-gray-900">{{ $instructor->name }}</span>
                            <span class="block text-xs text-gray-500">
                                {{ $instructor->courses_count }} {{ Str::plural('course', $instructor->courses_count) }} ·
                                {{ $instructor->lessons_count }} {{ Str::plural('lesson', $instructor->lessons_count) }}
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        @break
@endswitch
