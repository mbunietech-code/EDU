@props(['video', 'progress' => null])

@php
    /** @var \App\Models\LearningVideo $video */
    // Fall back to an eager-loaded progress relation; never query per card.
    $progress ??= $video->relationLoaded('progress') ? $video->progressFor(auth()->user()) : null;
    $completed = $progress?->isCompleted() ?? false;
    $percent = $progress ? (int) $progress->percent : 0;
@endphp

<a href="{{ route('learn.videos.show', $video) }}"
    {{ $attributes->merge(['class' => 'mbui-card group flex flex-col overflow-hidden transition hover:ring-2 hover:ring-indigo-500/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600']) }}>
    <div class="relative aspect-video w-full overflow-hidden bg-gray-100">
        @if ($video->thumbnailUrl())
            <x-learning.thumb :src="$video->thumbnailUrl()" :alt="$video->title" />
        @else
            <div class="flex h-full w-full items-center justify-center text-gray-400" aria-hidden="true">
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                </svg>
            </div>
        @endif

        @if ($video->duration_seconds)
            <span class="absolute bottom-2 right-2 rounded bg-gray-900/80 px-1.5 py-0.5 text-xs font-medium text-white">
                {{ $video->durationLabel() }}
            </span>
        @endif

        @if ($completed)
            <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-emerald-600 px-1.5 py-0.5 text-xs font-medium text-white">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Completed
            </span>
        @elseif ($percent > 0)
            <div class="absolute inset-x-0 bottom-0 h-1 bg-gray-900/30">
                <div class="h-1 bg-indigo-500" style="width: {{ min(100, $percent) }}%"></div>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="line-clamp-2 text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $video->title }}</h3>
        @if ($video->instructor)
            <p class="mt-1 truncate text-xs text-gray-600">{{ $video->instructor->name }}</p>
        @endif
        <div class="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-3 text-xs text-gray-500">
            @if ($video->category)
                <span class="truncate">{{ $video->category->name }}</span>
            @endif
            @if ($video->category && $video->published_at)
                <span aria-hidden="true">&middot;</span>
            @endif
            @if ($video->published_at)
                <time datetime="{{ $video->published_at->toIso8601String() }}">{{ $video->published_at->format('d M Y') }}</time>
            @endif
        </div>
        @if ($percent > 0 && ! $completed)
            <span class="sr-only">{{ $percent }}% watched</span>
        @endif
    </div>
</a>
