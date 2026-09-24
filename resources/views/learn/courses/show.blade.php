@php
    /** @var \App\Models\LearningCourse $course */
    $nextVideo = $progress['next_video'] ?? null;
    $firstLesson = $sections->isNotEmpty() ? $sections->first()['lessons']->first() : $otherLessons->first();
    $allDone = $progress['total'] > 0 && $progress['completed'] >= $progress['total'];
    $hours = intdiv($totalSeconds, 3600);
    $minutes = intdiv($totalSeconds % 3600, 60);
    $totalLabel = $totalSeconds <= 0 ? null : ($hours > 0 ? $hours.' h '.$minutes.' min' : max(1, $minutes).' min');
    $number = 0;
@endphp

<x-layouts.app :title="$course->title" header="Learning">

    <nav class="mb-4 flex min-w-0 items-center gap-1.5 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('learn.courses.index') }}" class="mbui-anchor shrink-0">Courses</a>
        @if ($course->category)
            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('learn.categories.show', $course->category) }}" class="mbui-anchor truncate">{{ $course->category->name }}</a>
        @endif
    </nav>

    @unless ($course->isPublished())
        <x-mbui.alert type="warning" class="mb-4">
            <strong class="font-semibold">Preview:</strong> this course is a draft and is not visible to learners yet.
        </x-mbui.alert>
    @endunless

    {{-- Hero --}}
    <section class="mbui-card overflow-hidden">
        <div class="grid gap-0 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <div class="relative aspect-video bg-gray-100 md:aspect-auto md:min-h-[16rem]">
                <x-learning.thumb :src="$course->thumbnailUrl()" :alt="$course->title" class="absolute inset-0" />
            </div>
            <div class="flex min-w-0 flex-col p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($course->category)
                        <x-mbui.badge appearance="info">{{ $course->category->name }}</x-mbui.badge>
                    @endif
                    <x-mbui.badge>{{ $course->levelLabel() }}</x-mbui.badge>
                    @if ($course->access === 'enrolled')
                        <x-mbui.badge appearance="warning">Enrolled learners only</x-mbui.badge>
                    @endif
                    @if ($enrollment)
                        <x-mbui.badge appearance="success">Enrolled</x-mbui.badge>
                    @endif
                </div>

                <h1 class="mt-3 text-xl font-semibold text-gray-900 sm:text-2xl">{{ $course->title }}</h1>

                @if ($course->instructor)
                    <p class="mt-1 text-sm text-gray-600">
                        by <a href="{{ route('learn.instructors.show', $course->instructor) }}" class="mbui-anchor">{{ $course->instructor->name }}</a>
                    </p>
                @endif

                @if ($course->summary)
                    <p class="mt-3 text-sm text-gray-700">{{ $course->summary }}</p>
                @endif

                <dl class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-600">
                    <div class="flex gap-1"><dt class="sr-only">Lessons</dt><dd><span class="font-semibold text-gray-900">{{ $lessonCount }}</span> {{ Str::plural('lesson', $lessonCount) }}</dd></div>
                    @if ($totalLabel)
                        <div class="flex gap-1"><dt class="sr-only">Total length</dt><dd><span class="font-semibold text-gray-900">{{ $totalLabel }}</span> of video</dd></div>
                    @endif
                    @if ($sections->count())
                        <div class="flex gap-1"><dt class="sr-only">Topics</dt><dd><span class="font-semibold text-gray-900">{{ $sections->count() }}</span> {{ Str::plural('topic', $sections->count()) }}</dd></div>
                    @endif
                </dl>

                <div class="mt-auto pt-5">
                    @if ($progress['total'] > 0)
                        <x-learning.progress-bar size="md" :percent="$progress['percent']"
                            :label="$progress['completed'].' of '.$progress['total'].' lessons completed'" />
                    @endif

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        @if ($nextVideo)
                            <x-mbui.btn-link :href="route('learn.videos.show', $nextVideo)">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                                </svg>
                                {{ $progress['started'] ? 'Continue learning' : 'Start course' }}
                            </x-mbui.btn-link>
                        @elseif ($allDone && $firstLesson)
                            <x-mbui.btn-link :href="route('learn.videos.show', $firstLesson)" variant="success">Course completed · Watch again</x-mbui.btn-link>
                        @endif

                        @if (! $enrollment && $canEnroll)
                            <form method="POST" action="{{ route('learn.courses.enroll', $course) }}">
                                @csrf
                                <x-mbui.button type="submit" :variant="$nextVideo ? 'secondary' : 'primary'" class="w-full sm:w-auto">Enrol in this course</x-mbui.button>
                            </form>
                        @elseif ($enrollment && $enrollment->source === 'self')
                            <div x-data="{ confirming: false }" class="flex flex-wrap items-center gap-2">
                                <x-mbui.button variant="ghost" x-show="! confirming" @click="confirming = true">Leave course</x-mbui.button>
                                <form x-show="confirming" x-cloak method="POST" action="{{ route('learn.courses.unenroll', $course) }}"
                                    class="flex flex-wrap items-center gap-2 rounded-lg bg-red-50 px-3 py-2">
                                    @csrf
                                    @method('DELETE')
                                    <span class="text-sm text-red-800">Leave this course? Your lesson progress is kept.</span>
                                    <x-mbui.button type="submit" variant="danger" class="!px-3 !py-1.5">Leave</x-mbui.button>
                                    <x-mbui.button variant="ghost" class="!px-3 !py-1.5" @click="confirming = false">Cancel</x-mbui.button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @if ($enrollment && $enrollment->source !== 'self')
                        <p class="mt-3 text-xs text-gray-500">An administrator enrolled you in this course.</p>
                    @elseif (! $enrollment && $course->access === 'enrolled')
                        <p class="mt-3 text-xs text-gray-500">Enrolment in this course is arranged by an administrator.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Curriculum --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($course->descriptionHtml())
                <section class="mbui-card p-5 sm:p-6" aria-labelledby="course-about">
                    <h2 id="course-about" class="mbui-section-label">About this course</h2>
                    <div class="research-prose mt-3 break-words text-sm text-gray-700">{!! $course->descriptionHtml() !!}</div>
                </section>
            @endif

            <section aria-labelledby="course-curriculum">
                <h2 id="course-curriculum" class="mbui-section-label mb-3">Lessons</h2>

                @if ($lessonCount === 0)
                    <x-learning.empty title="No lessons yet" message="Lessons will appear here once the instructor publishes them." />
                @else
                    <div class="space-y-4">
                        @foreach ($sections as $section)
                            @php($done = $section['lessons']->filter(fn ($l) => $l->progressFor(auth()->user())?->isCompleted())->count())
                            <div class="mbui-card overflow-hidden">
                                <div class="flex items-start justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ $section['topic']->title }}</h3>
                                        @if ($section['topic']->description)
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $section['topic']->description }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 text-xs text-gray-500">{{ $done }}/{{ $section['lessons']->count() }}</span>
                                </div>
                                <ol class="divide-y divide-gray-100">
                                    @foreach ($section['lessons'] as $lesson)
                                        @include('learn.courses.partials.lesson-row', ['lesson' => $lesson, 'number' => ++$number, 'nextId' => $nextVideo?->id])
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach

                        @if ($otherLessons->isNotEmpty())
                            <div class="mbui-card overflow-hidden">
                                <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $sections->isEmpty() ? 'All lessons' : 'Other lessons' }}</h3>
                                </div>
                                <ol class="divide-y divide-gray-100">
                                    @foreach ($otherLessons as $lesson)
                                        @include('learn.courses.partials.lesson-row', ['lesson' => $lesson, 'number' => ++$number, 'nextId' => $nextVideo?->id])
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        {{-- Sidebar --}}
        <aside class="min-w-0 space-y-6">
            <section class="mbui-card p-5" aria-labelledby="course-live">
                <h2 id="course-live" class="mbui-section-label">Live sessions</h2>
                @if ($upcomingRooms->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No upcoming live sessions for this course.</p>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach ($upcomingRooms as $room)
                            <x-learning.room-card :room="$room" />
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($course->instructor)
                <section class="mbui-card p-5" aria-labelledby="course-instructor">
                    <h2 id="course-instructor" class="mbui-section-label">Instructor</h2>
                    <a href="{{ route('learn.instructors.show', $course->instructor) }}" class="mt-3 flex items-center gap-3 rounded-lg p-1 hover:bg-gray-50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700" aria-hidden="true">
                            {{ Str::upper(Str::substr($course->instructor->name, 0, 1)) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-900">{{ $course->instructor->name }}</span>
                            <span class="block text-xs text-indigo-600">View profile</span>
                        </span>
                    </a>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
