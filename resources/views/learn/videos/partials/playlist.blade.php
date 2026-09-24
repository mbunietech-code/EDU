{{-- Course playlist. Expects $playlist (sections: title + lessons), $course, $video. --}}
@php
    $allLessons = $playlist->flatMap(fn ($s) => $s['lessons']);
    $doneCount = $allLessons->filter(fn ($l) => $l->progressFor(auth()->user())?->isCompleted())->count();
    $number = 0;
@endphp

<section class="mbui-card overflow-hidden" aria-labelledby="{{ $playlistId ?? 'playlist-title' }}">
    <div class="border-b border-gray-100 px-4 py-3">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Course content</p>
        <h2 id="{{ $playlistId ?? 'playlist-title' }}" class="mt-0.5 text-sm font-semibold text-gray-900">
            <a href="{{ route('learn.courses.show', $course) }}" class="hover:text-indigo-600">{{ $course->title }}</a>
        </h2>
        <x-learning.progress-bar class="mt-2" :percent="$allLessons->count() ? $doneCount / $allLessons->count() * 100 : 0"
            :label="$doneCount.' of '.$allLessons->count().' completed'" />
    </div>

    <div class="max-h-[28rem] overflow-y-auto lg:max-h-[60vh]">
        @foreach ($playlist as $section)
            <div>
                <h3 class="sticky top-0 z-10 border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-700">{{ $section['title'] }}</h3>
                <ol class="divide-y divide-gray-50">
                    @foreach ($section['lessons'] as $lesson)
                        @php
                            $number++;
                            $current = $lesson->id === $video->id;
                            $lessonDone = $lesson->progressFor(auth()->user())?->isCompleted() ?? false;
                        @endphp
                        <li>
                            <a href="{{ route('learn.videos.show', $lesson) }}"
                                @if ($current) aria-current="page" @endif
                                @class([
                                    'flex items-center gap-3 px-4 py-2.5 text-sm transition',
                                    'bg-indigo-50 text-indigo-700' => $current,
                                    'text-gray-700 hover:bg-gray-50' => ! $current,
                                ])>
                                <span @class([
                                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-700' => $lessonDone,
                                    'bg-indigo-600 text-white' => ! $lessonDone && $current,
                                    'bg-gray-100 text-gray-600' => ! $lessonDone && ! $current,
                                ])>
                                    @if ($lessonDone)
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                        <span class="sr-only">Completed:</span>
                                    @elseif ($current)
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M6 4.5v15l13-7.5-13-7.5z" />
                                        </svg>
                                        <span class="sr-only">Now playing:</span>
                                    @else
                                        {{ $number }}
                                    @endif
                                </span>
                                <span @class(['min-w-0 flex-1 truncate', 'font-semibold' => $current])>{{ $lesson->title }}</span>
                                <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ $lesson->durationLabel() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
</section>
