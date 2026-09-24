<x-layouts.app title="My Learning" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">My Learning</h1>
            <p class="mt-1 text-sm text-gray-500">Pick up where you left off, join live classes and track your progress.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-mbui.btn-link :href="route('learn.calendar')" variant="secondary">Calendar</x-mbui.btn-link>
            <x-mbui.btn-link :href="route('learn.progress')" variant="secondary">My progress</x-mbui.btn-link>
            <x-mbui.btn-link :href="route('learn.courses.index')">Browse courses</x-mbui.btn-link>
        </div>
    </div>

    <div class="mt-6">
        @include('learn.partials.search-box', ['id' => 'dashboard-search'])
    </div>

    {{-- Learning progress summary --}}
    <section class="mt-6" aria-labelledby="dash-stats">
        <h2 id="dash-stats" class="sr-only">Learning progress summary</h2>
        @include('learn.partials.stats', ['stats' => $stats])
    </section>

    {{-- Live now --}}
    <section class="mt-8" aria-labelledby="dash-live">
        @include('learn.partials.section-header', [
            'id' => 'dash-live', 'title' => 'Live now',
            'href' => $liveRooms->isNotEmpty() ? route('learn.rooms.index', ['tab' => 'live']) : null,
        ])
        <div class="mt-3">
            @if ($liveRooms->isEmpty())
                <x-learning.empty title="No live classes right now."
                    message="When a class you can join goes live it appears here."
                    :action-href="route('learn.rooms.index', ['tab' => 'upcoming'])" action-label="See upcoming classes" />
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($liveRooms as $room)
                        <x-learning.room-card :room="$room" class="ring-2 ring-red-500/30" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Continue learning --}}
    <section class="mt-8" aria-labelledby="dash-continue">
        @include('learn.partials.section-header', ['id' => 'dash-continue', 'title' => 'Continue learning'])
        <div class="mt-3">
            @if ($continueWatching->isEmpty())
                <x-learning.empty title="You haven't started any lessons yet."
                    message="Lessons you start watching are saved here so you can resume exactly where you stopped."
                    :action-href="route('learn.videos.index')" action-label="Browse lessons" />
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($continueWatching as $row)
                        <x-learning.video-card :video="$row->video" :progress="$row" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        {{-- My courses --}}
        <section class="lg:col-span-2" aria-labelledby="dash-courses">
            @include('learn.partials.section-header', [
                'id' => 'dash-courses', 'title' => 'My courses',
                'href' => $myCourses->isNotEmpty() ? route('learn.progress') : null, 'linkLabel' => 'All progress',
            ])
            <div class="mt-3 space-y-3">
                @forelse ($myCourses as $course)
                    @include('learn.partials.course-progress', ['course' => $course, 'progress' => $courseProgress[$course->id] ?? []])
                @empty
                    <x-learning.empty title="You are not following any course yet."
                        message="Enrol in a course or start one of its lessons and it will appear here with your progress."
                        :action-href="route('learn.courses.index')" action-label="Find a course" />
                @endforelse
            </div>
        </section>

        {{-- Upcoming live sessions --}}
        <section aria-labelledby="dash-upcoming">
            @include('learn.partials.section-header', [
                'id' => 'dash-upcoming', 'title' => 'Upcoming live sessions',
                'href' => route('learn.calendar'), 'linkLabel' => 'Calendar',
            ])
            <div class="mt-3">
                @if ($upcomingRooms->isEmpty())
                    <x-learning.empty title="No upcoming classes." message="Scheduled live sessions you can attend will be listed here." />
                @else
                    <ul class="mbui-card divide-y divide-gray-100">
                        @foreach ($upcomingRooms as $room)
                            <li class="flex items-start gap-3 p-4">
                                <div class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-indigo-50 py-1.5 text-indigo-700" aria-hidden="true">
                                    <span class="text-[10px] font-semibold uppercase">{{ $room->scheduled_at?->format('M') }}</span>
                                    <span class="text-lg font-bold leading-none">{{ $room->scheduled_at?->format('d') }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('learn.rooms.show', $room) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $room->title }}</a>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        @if ($room->scheduled_at)
                                            <time datetime="{{ $room->scheduled_at->toIso8601String() }}">{{ $room->scheduled_at->format('D, d M · H:i') }}</time>
                                        @endif
                                        @if ($room->host) · {{ $room->host->name }} @endif
                                    </p>
                                </div>
                                @if ($room->scheduled_at)
                                    <a href="{{ route('learn.rooms.ics', $room) }}" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600"
                                        aria-label="Add {{ $room->title }} to your calendar" title="Add to calendar">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m-2.25-10.5v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                        </svg>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>

    {{-- Recently watched --}}
    <section class="mt-8" aria-labelledby="dash-recent">
        @include('learn.partials.section-header', ['id' => 'dash-recent', 'title' => 'Recently watched'])
        <div class="mt-3">
            @if ($recentlyWatched->isEmpty())
                <x-learning.empty title="Nothing watched yet." message="Your watch history will show up here." />
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($recentlyWatched as $row)
                        <x-learning.video-card :video="$row->video" :progress="$row" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        {{-- Completed lessons --}}
        <section class="lg:col-span-2" aria-labelledby="dash-completed">
            @include('learn.partials.section-header', [
                'id' => 'dash-completed', 'title' => 'Completed lessons',
                'href' => $completedLessons->isNotEmpty() ? route('learn.progress').'#completed' : null,
            ])
            <div class="mt-3">
                @if ($completedLessons->isEmpty())
                    <x-learning.empty title="No completed lessons yet." message="Finish a lesson to see it here — you can always watch it again." />
                @else
                    <ul class="mbui-card divide-y divide-gray-100">
                        @foreach ($completedLessons as $row)
                            @include('learn.partials.completed-row', ['row' => $row])
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Available categories --}}
        <section aria-labelledby="dash-categories">
            @include('learn.partials.section-header', [
                'id' => 'dash-categories', 'title' => 'Available categories', 'href' => route('learn.categories.index'),
            ])
            <div class="mt-3">
                @if ($categories->isEmpty())
                    <x-learning.empty title="No categories available yet." message="New learning areas will appear here once content is published." />
                @else
                    <ul class="mbui-card divide-y divide-gray-100">
                        @foreach ($categories as $category)
                            <li>
                                <a href="{{ route('learn.categories.show', $category) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                                    <span class="truncate text-sm font-medium text-gray-900">{{ $category->name }}</span>
                                    <span class="shrink-0 text-xs text-gray-500">
                                        {{ $category->courses_count }} {{ Str::plural('course', $category->courses_count) }} ·
                                        {{ $category->videos_count }} {{ Str::plural('lesson', $category->videos_count) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
</x-layouts.app>
