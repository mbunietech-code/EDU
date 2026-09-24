{{-- Instructor profile. Only the name, "member since" and content the viewer may open — never the email. --}}
<x-layouts.app :title="$instructor->name" header="Learning">

    <section class="mbui-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-2xl font-semibold text-indigo-700" aria-hidden="true">
                {{ Str::upper(Str::substr($instructor->name, 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="mbui-title break-words">{{ $instructor->name }}</h1>
                    @if ($instructor->isInstructor())
                        <x-mbui.badge appearance="info">Instructor</x-mbui.badge>
                    @endif
                </div>
                @if ($instructor->created_at)
                    <p class="mt-1 text-sm text-gray-500">Member since {{ $instructor->created_at->format('F Y') }}</p>
                @endif
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-3 gap-3 border-t border-gray-100 pt-5 text-center">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Courses</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $counts['courses'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Lessons</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $counts['lessons'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Upcoming live</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $counts['rooms'] }}</dd>
            </div>
        </dl>
    </section>

    @if ($rooms->isNotEmpty())
        <section class="mt-8" aria-labelledby="instructor-rooms">
            <h2 id="instructor-rooms" class="mbui-section-label mb-3">Live sessions</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rooms as $room)
                    <x-learning.room-card :room="$room" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="instructor-courses">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 id="instructor-courses" class="mbui-section-label">Courses</h2>
            @if ($counts['courses'] > $courses->count())
                <span class="text-xs text-gray-500">Showing {{ $courses->count() }} of {{ $counts['courses'] }}</span>
            @endif
        </div>
        @if ($courses->isEmpty())
            <x-learning.empty title="No courses yet" :message="$instructor->name.' has no published courses you can open.'" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($courses as $course)
                    <x-learning.course-card :course="$course" :progress="$courseProgress[$course->id] ?? null" />
                @endforeach
            </div>
        @endif
    </section>

    <section id="lessons" class="mt-8 scroll-mt-24" aria-labelledby="instructor-lessons">
        <h2 id="instructor-lessons" class="mbui-section-label mb-3">Video lessons</h2>
        @if ($lessons->isEmpty())
            <x-learning.empty title="No lessons yet" :message="'Published lessons by '.$instructor->name.' will appear here.'" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($lessons as $video)
                    <x-learning.video-card :video="$video" />
                @endforeach
            </div>
            <div class="mt-6">{{ $lessons->fragment('lessons')->links() }}</div>
        @endif
    </section>
</x-layouts.app>
