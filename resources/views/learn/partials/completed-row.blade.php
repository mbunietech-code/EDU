{{-- A completed lesson (LearningVideoProgress with video). Completed lessons stay open for repeat study. Var: $row --}}
@php($video = $row->video)
<li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex min-w-0 items-start gap-3">
        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </span>
        <div class="min-w-0">
            <a href="{{ route('learn.videos.show', $video) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $video->title }}</a>
            <p class="mt-0.5 flex flex-wrap gap-x-2 text-xs text-gray-500">
                @if ($video->course)
                    <span class="truncate">{{ $video->course->title }}</span>
                @elseif ($video->category)
                    <span class="truncate">{{ $video->category->name }}</span>
                @endif
                @if ($video->duration_seconds)
                    <span>{{ $video->durationLabel() }}</span>
                @endif
                <span>Completed <time datetime="{{ $row->completed_at->toIso8601String() }}">{{ $row->completed_at->format('d M Y') }}</time></span>
            </p>
        </div>
    </div>
    <a href="{{ route('learn.videos.show', $video) }}" class="inline-flex shrink-0 items-center gap-1 self-start text-sm font-medium text-indigo-600 hover:text-indigo-500 sm:self-center"
        aria-label="Watch again: {{ $video->title }}">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        Watch again
    </a>
</li>
