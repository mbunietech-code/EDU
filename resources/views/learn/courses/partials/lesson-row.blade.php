@php
    /** @var \App\Models\LearningVideo $lesson */
    $lessonProgress = $lesson->progressFor(auth()->user());
    $done = $lessonProgress?->isCompleted() ?? false;
    $lessonPercent = $lessonProgress ? (int) $lessonProgress->percent : 0;
    $isNext = isset($nextId) && $nextId === $lesson->id;
@endphp

<li>
    <a href="{{ route('learn.videos.show', $lesson) }}"
        class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600">
        <span @class([
            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
            'bg-emerald-100 text-emerald-700' => $done,
            'bg-indigo-100 text-indigo-700' => ! $done && $lessonPercent > 0,
            'bg-gray-100 text-gray-600' => ! $done && $lessonPercent === 0,
        ])>
            @if ($done)
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span class="sr-only">Completed:</span>
            @else
                {{ $number }}
            @endif
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-gray-900">{{ $lesson->title }}</span>
            @if (! $done && $lessonPercent > 0)
                <span class="mt-0.5 block text-xs text-indigo-600">{{ $lessonPercent }}% watched</span>
            @elseif ($isNext)
                <span class="mt-0.5 block text-xs text-indigo-600">Up next</span>
            @endif
        </span>
        <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ $lesson->durationLabel() }}</span>
    </a>
</li>
