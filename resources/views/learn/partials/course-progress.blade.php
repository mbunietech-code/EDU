{{-- One course with its roll-up. Vars: $course (LearningCourse), $progress (ProgressService::courseProgress array), $enrollment = null --}}
@php
    $total = (int) ($progress['total'] ?? 0);
    $done = (int) ($progress['completed'] ?? 0);
    $next = $progress['next_video'] ?? null;
    $finished = $total > 0 && $done >= $total;
@endphp
<div class="mbui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
    <a href="{{ route('learn.courses.show', $course) }}" class="hidden h-16 w-28 shrink-0 overflow-hidden rounded-lg bg-gray-100 sm:block" tabindex="-1" aria-hidden="true">
        <x-learning.thumb :src="$course->thumbnailUrl()" alt="" />
    </a>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            @if ($course->category)
                <span class="truncate text-xs font-medium uppercase tracking-wide text-indigo-600">{{ $course->category->name }}</span>
            @endif
            @if ($finished)
                <x-mbui.badge appearance="success">Completed</x-mbui.badge>
            @elseif ($progress['started'] ?? false)
                <x-mbui.badge appearance="info">In progress</x-mbui.badge>
            @else
                <x-mbui.badge appearance="neutral">Not started</x-mbui.badge>
            @endif
            @if (isset($enrollment) && $enrollment)
                <span class="text-xs text-gray-500">Enrolled {{ $enrollment->enrolled_at?->format('d M Y') ?? $enrollment->created_at?->format('d M Y') }}</span>
            @endif
        </div>
        <h3 class="mt-1 truncate text-sm font-semibold text-gray-900">
            <a href="{{ route('learn.courses.show', $course) }}" class="hover:text-indigo-600">{{ $course->title }}</a>
        </h3>
        <x-learning.progress-bar class="mt-2" size="md" :percent="$progress['percent'] ?? 0"
            :label="$done.' / '.$total.' '.\Illuminate\Support\Str::plural('lesson', $total).' completed'" />
    </div>
    <div class="shrink-0">
        @if ($next)
            <x-mbui.btn-link :href="route('learn.videos.show', $next)" variant="secondary" class="w-full sm:w-auto">
                {{ ($progress['started'] ?? false) ? 'Continue' : 'Start' }}
            </x-mbui.btn-link>
        @else
            <x-mbui.btn-link :href="route('learn.courses.show', $course)" variant="ghost" class="w-full sm:w-auto">Open course</x-mbui.btn-link>
        @endif
    </div>
</div>
