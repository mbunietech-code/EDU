@php
    /** @var \App\Models\LearningVideo $video */
    $isDone = $progress?->isCompleted() ?? false;
    $watched = $progress ? (int) $progress->percent : 0;
@endphp

<x-layouts.app :title="$video->title" header="Learning">

    {{-- Breadcrumb: category / course / topic --}}
    <nav class="mb-4 flex min-w-0 flex-wrap items-center gap-1.5 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('learn.videos.index') }}" class="mbui-anchor shrink-0">Lessons</a>
        @if ($course)
            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('learn.courses.show', $course) }}" class="mbui-anchor min-w-0 max-w-full truncate">{{ $course->title }}</a>
            @if ($video->topic && (int) $video->topic->learning_course_id === (int) $course->id)
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
                <span class="min-w-0 max-w-full truncate text-gray-700">{{ $video->topic->title }}</span>
            @endif
        @elseif ($video->category)
            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('learn.categories.show', $video->category) }}" class="mbui-anchor min-w-0 max-w-full truncate">{{ $video->category->name }}</a>
        @endif
    </nav>

    @if (count($preview))
        <x-mbui.alert type="warning" class="mb-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="font-semibold">Preview — learners cannot see this lesson right now.</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($preview as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
                @if ($canManage)
                    <a href="{{ route('studio.videos.edit', $video) }}" class="shrink-0 font-semibold text-amber-900 underline hover:no-underline">Edit in Studio</a>
                @endif
            </div>
        </x-mbui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('learn.videos.partials.player')

            {{-- Details --}}
            <section class="mbui-card p-5 sm:p-6" aria-labelledby="lesson-title">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($isDone)
                        <x-mbui.badge appearance="success">Completed</x-mbui.badge>
                    @elseif ($watched > 0)
                        <x-mbui.badge appearance="info">{{ $watched }}% watched</x-mbui.badge>
                    @else
                        <x-mbui.badge>Not started</x-mbui.badge>
                    @endif
                    @unless ($video->isPublished())
                        <x-mbui.badge appearance="warning">Draft</x-mbui.badge>
                    @endunless
                    @if ($video->visibility === 'private')
                        <x-mbui.badge appearance="danger">Private</x-mbui.badge>
                    @elseif ($video->visibility === 'members' && $video->learning_course_id)
                        <x-mbui.badge appearance="info">Free preview</x-mbui.badge>
                    @endif
                </div>

                <h1 id="lesson-title" class="mt-3 break-words text-xl font-semibold text-gray-900 sm:text-2xl">{{ $video->title }}</h1>

                <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-gray-600">
                    @if ($video->instructor)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Instructor</dt>
                            <dd><a href="{{ route('learn.instructors.show', $video->instructor) }}" class="mbui-anchor">{{ $video->instructor->name }}</a></dd>
                        </div>
                    @endif
                    @if ($video->category)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Category</dt>
                            <dd><a href="{{ route('learn.categories.show', $video->category) }}" class="hover:text-indigo-600">{{ $video->category->name }}</a></dd>
                        </div>
                    @endif
                    @if ($video->published_at)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Published</dt>
                            <dd><time datetime="{{ $video->published_at->toIso8601String() }}">{{ $video->published_at->format('d M Y') }}</time></dd>
                        </div>
                    @endif
                    <div class="flex items-center gap-1">
                        <dt class="sr-only">Duration</dt>
                        <dd class="inline-flex items-center gap-1">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ $video->durationLabel() }}
                        </dd>
                    </div>
                    <div class="flex items-center gap-1">
                        <dt class="sr-only">Views</dt>
                        <dd class="inline-flex items-center gap-1">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ number_format((int) $video->views) }} {{ Str::plural('view', (int) $video->views) }}
                        </dd>
                    </div>
                </dl>

                @if ($video->descriptionHtml())
                    <div class="research-prose mt-4 break-words border-t border-gray-100 pt-4 text-sm text-gray-700">{!! $video->descriptionHtml() !!}</div>
                @endif

                @if (! empty($video->tags))
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ((array) $video->tags as $tag)
                            <a href="{{ route('learn.videos.index', ['q' => $tag]) }}"
                                class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200">#{{ $tag }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($previous || $next)
                    <div class="mt-5 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-between">
                        @if ($previous)
                            <a href="{{ route('learn.videos.show', $previous) }}" class="group flex min-w-0 items-center gap-2 text-sm text-gray-600 hover:text-indigo-600">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                                <span class="min-w-0 truncate"><span class="text-gray-400">Previous:</span> {{ $previous->title }}</span>
                            </a>
                        @else
                            <span></span>
                        @endif
                        @if ($next)
                            <a href="{{ route('learn.videos.show', $next) }}" class="group flex min-w-0 items-center justify-end gap-2 text-sm text-gray-600 hover:text-indigo-600">
                                <span class="min-w-0 truncate"><span class="text-gray-400">Next:</span> {{ $next->title }}</span>
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        @endif
                    </div>
                @endif
            </section>

            @include('learn.videos.partials.resources')

            {{-- On small screens the playlist sits above the discussion. --}}
            @if ($playlist->isNotEmpty())
                <div class="lg:hidden">
                    @include('learn.videos.partials.playlist', ['playlistId' => 'playlist-title-mobile'])
                </div>
            @endif

            @include('learn.videos.partials.comments')
        </div>

        <aside class="min-w-0 space-y-6">
            @if ($playlist->isNotEmpty())
                <div class="hidden lg:block">
                    @include('learn.videos.partials.playlist')
                </div>
            @endif

            <section class="mbui-card p-5" aria-labelledby="related-title">
                <h2 id="related-title" class="mbui-section-label">{{ $course ? 'More in this course' : 'Related lessons' }}</h2>
                @if ($related->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No related lessons yet.</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($related as $item)
                            @php($itemProgress = $item->progressFor(auth()->user()))
                            <li>
                                <a href="{{ route('learn.videos.show', $item) }}" class="group flex gap-3 rounded-lg p-1 hover:bg-gray-50">
                                    <span class="relative block aspect-video w-28 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                        <x-learning.thumb :src="$item->thumbnailUrl()" alt="" />
                                        @if ($item->duration_seconds)
                                            <span class="absolute bottom-1 right-1 rounded bg-gray-900/80 px-1 text-[10px] font-medium text-white">{{ $item->durationLabel() }}</span>
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $item->title }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $item->instructor?->name ?? $item->category?->name }}</span>
                                        @if ($itemProgress?->isCompleted())
                                            <span class="mt-0.5 block text-xs font-medium text-emerald-700">Completed</span>
                                        @elseif ($itemProgress && $itemProgress->percent > 0)
                                            <span class="mt-0.5 block text-xs text-indigo-600">{{ (int) $itemProgress->percent }}% watched</span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
