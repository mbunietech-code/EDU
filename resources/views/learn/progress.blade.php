<x-layouts.app title="My Progress" header="Learning">
    @php($dur = fn (int $s) => \App\Http\Controllers\Learn\DashboardController::durationText($s))

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">My progress</h1>
            <p class="mt-1 text-sm text-gray-500">Your courses, completed lessons and live-class attendance.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.dashboard')" variant="secondary">My Learning</x-mbui.btn-link>
        </div>
    </div>

    <section class="mt-6" aria-labelledby="progress-overall">
        <h2 id="progress-overall" class="sr-only">Overall statistics</h2>
        @include('learn.partials.stats', ['stats' => $stats])
        <p class="mt-3 text-sm text-gray-500">
            Live classes attended: <span class="font-medium text-gray-700">{{ number_format($sessionsAttended) }}</span>
            · Time in live classes: <span class="font-medium text-gray-700">{{ $dur($attendedSeconds) }}</span>
        </p>
    </section>

    {{-- Per-course progress --}}
    <section class="mt-8" aria-labelledby="progress-courses">
        @include('learn.partials.section-header', ['id' => 'progress-courses', 'title' => 'Courses', 'count' => $courses->total()])
        <div class="mt-3 space-y-3">
            @forelse ($courses as $course)
                @include('learn.partials.course-progress', [
                    'course' => $course,
                    'progress' => $courseProgress[$course->id] ?? [],
                    'enrollment' => $enrollments[$course->id] ?? null,
                ])
            @empty
                <x-learning.empty title="You are not following any course yet."
                    message="Courses you enrol in or start watching appear here with your progress."
                    :action-href="route('learn.courses.index')" action-label="Browse courses" />
            @endforelse
        </div>
        @if ($courses->hasPages())
            <div class="mt-4">{{ $courses->links() }}</div>
        @endif
    </section>

    {{-- Completed lessons --}}
    <section id="completed" class="mt-8 scroll-mt-20" aria-labelledby="progress-completed">
        @include('learn.partials.section-header', ['id' => 'progress-completed', 'title' => 'Completed lessons', 'count' => $completed->total()])
        <div class="mt-3">
            @if ($completed->isEmpty())
                <x-learning.empty title="No completed lessons yet."
                    message="Lessons you finish are listed here and stay open so you can watch them again."
                    :action-href="route('learn.videos.index')" action-label="Browse lessons" />
            @else
                <ul class="mbui-card divide-y divide-gray-100">
                    @foreach ($completed as $row)
                        @include('learn.partials.completed-row', ['row' => $row])
                    @endforeach
                </ul>
                @if ($completed->hasPages())
                    <div class="mt-4">{{ $completed->fragment('completed')->links() }}</div>
                @endif
            @endif
        </div>
    </section>

    {{-- Attendance history --}}
    <section id="attendance" class="mt-8 scroll-mt-20" aria-labelledby="progress-attendance">
        @include('learn.partials.section-header', ['id' => 'progress-attendance', 'title' => 'Live class attendance', 'count' => $attendance->total()])
        <div class="mt-3">
            @if ($attendance->isEmpty())
                <x-learning.empty title="You haven't attended a live class yet."
                    message="Sessions you join are recorded here with the time you spent in them."
                    :action-href="route('learn.rooms.index')" action-label="See live classes" />
            @else
                <div class="mbui-card overflow-hidden">
                    {{-- Table on md+, stacked cards below --}}
                    <table class="hidden w-full md:table">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50">
                                <th class="mbui-th">Class</th>
                                <th class="mbui-th">Session</th>
                                <th class="mbui-th">Joined</th>
                                <th class="mbui-th">Time attended</th>
                                <th class="mbui-th">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($attendance as $row)
                                <tr>
                                    <td class="mbui-td">
                                        @if (in_array((int) $row->learning_room_id, $visibleRoomIds, true))
                                            <a href="{{ route('learn.rooms.show', $row->room) }}" class="mbui-anchor">{{ $row->room->title }}</a>
                                        @else
                                            <span class="text-gray-900">{{ $row->room->title }}</span>
                                        @endif
                                        @if ($row->room->host)
                                            <span class="block text-xs text-gray-500">{{ $row->room->host->name }}</span>
                                        @endif
                                    </td>
                                    <td class="mbui-td text-sm text-gray-600">{{ $row->session?->started_at?->format('d M Y · H:i') ?? '—' }}</td>
                                    <td class="mbui-td text-sm text-gray-600">{{ $row->first_joined_at?->format('H:i') ?? '—' }}
                                        @if ($row->join_count > 1)<span class="text-xs text-gray-400">({{ $row->join_count }}×)</span>@endif
                                    </td>
                                    <td class="mbui-td text-sm font-medium text-gray-900">{{ $dur((int) $row->total_seconds) }}</td>
                                    <td class="mbui-td">
                                        @if ($row->removed_at)
                                            <x-mbui.badge appearance="danger">Removed</x-mbui.badge>
                                        @elseif ($row->role === 'host')
                                            <x-mbui.badge appearance="info">Host</x-mbui.badge>
                                        @else
                                            <x-mbui.badge appearance="success">Attended</x-mbui.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <ul class="divide-y divide-gray-100 md:hidden">
                        @foreach ($attendance as $row)
                            <li class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if (in_array((int) $row->learning_room_id, $visibleRoomIds, true))
                                            <a href="{{ route('learn.rooms.show', $row->room) }}" class="block truncate text-sm font-medium text-indigo-600">{{ $row->room->title }}</a>
                                        @else
                                            <span class="block truncate text-sm font-medium text-gray-900">{{ $row->room->title }}</span>
                                        @endif
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $row->session?->started_at?->format('d M Y · H:i') ?? '—' }}</p>
                                    </div>
                                    <span class="shrink-0 text-sm font-medium text-gray-900">{{ $dur((int) $row->total_seconds) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @if ($attendance->hasPages())
                    <div class="mt-4">{{ $attendance->fragment('attendance')->links() }}</div>
                @endif
            @endif
        </div>
    </section>
</x-layouts.app>
