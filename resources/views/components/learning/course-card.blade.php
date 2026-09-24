@props(['course', 'progress' => null])

@php
    /** @var \App\Models\LearningCourse $course */
    // $progress is the array from ProgressService::courseProgress().
    $lessons = $course->videos_count ?? null;
@endphp

<a href="{{ route('learn.courses.show', $course) }}"
    {{ $attributes->merge(['class' => 'mbui-card group flex flex-col overflow-hidden transition hover:ring-2 hover:ring-indigo-500/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600']) }}>
    <div class="relative aspect-video w-full overflow-hidden bg-gray-100">
        <x-learning.thumb :src="$course->thumbnailUrl()" :alt="$course->title" />
        @if ($course->level)
            <span class="absolute left-2 top-2 rounded-md bg-white/90 px-1.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-200">
                {{ $course->levelLabel() }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        @if ($course->category)
            <p class="truncate text-xs font-medium uppercase tracking-wide text-indigo-600">{{ $course->category->name }}</p>
        @endif
        <h3 class="mt-1 line-clamp-2 text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $course->title }}</h3>
        @if ($course->summary)
            <p class="mt-1 line-clamp-2 text-xs text-gray-600">{{ $course->summary }}</p>
        @endif

        <div class="mt-auto pt-3">
            @if ($lessons !== null)
                <p class="text-xs text-gray-500">{{ $lessons }} {{ \Illuminate\Support\Str::plural('lesson', (int) $lessons) }}</p>
            @endif
            @if (is_array($progress) && ($progress['total'] ?? 0) > 0)
                <x-learning.progress-bar class="mt-2" :percent="$progress['percent'] ?? 0"
                    :label="($progress['completed'] ?? 0).' of '.$progress['total'].' done'" />
            @endif
        </div>
    </div>
</a>
