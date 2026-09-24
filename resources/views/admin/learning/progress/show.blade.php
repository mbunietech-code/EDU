<x-layouts.admin :title="'Progress · '.$user->name" header="Learning">

    <div class="mbui-page-header">
        <div class="min-w-0">
            <nav class="text-xs text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('admin.learning.progress.index') }}" class="hover:text-indigo-600">Learner progress</a>
            </nav>
            <h1 class="mbui-title">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $user->email }} · Member since {{ $user->created_at?->format('d M Y') }}
                @if ($user->isInstructor()) · <span class="text-indigo-700">Instructor</span>@endif
            </p>
        </div>
        <div class="flex gap-2">
            @can('users.view')
                <x-mbui.btn-link :href="route('admin.users.show', $user)" variant="secondary">Member profile</x-mbui.btn-link>
            @endcan
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-mbui.stats-card title="Courses enrolled" :value="number_format($stats['courses_enrolled'])" :trend="number_format($stats['courses_completed']).' completed'" />
        <x-mbui.stats-card title="Lessons started" :value="number_format($stats['started'])" />
        <x-mbui.stats-card title="Lessons completed" :value="number_format($stats['completed'])" />
        <x-mbui.stats-card title="Watch time" :value="\App\Services\Learning\LearningAnalytics::duration($stats['watch_seconds'])" />
        <x-mbui.stats-card title="Live sessions" :value="number_format($attendance->total())" trend="Sessions attended" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-mbui.card class="p-0">
            <h2 class="border-b border-gray-200 px-4 py-4 text-base font-semibold text-gray-900 sm:px-6">Courses</h2>
            @if ($courses->isEmpty())
                <x-mbui.empty-state title="No courses yet" message="Not enrolled and no lessons started in any course." />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($courses as $row)
                        <li class="px-4 py-4 sm:px-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.learning.courses.show', $row['course']) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $row['course']->title }}</a>
                                    <p class="text-xs text-gray-500">{{ $row['course']->category?->name ?? '—' }} ·
                                        @if ($row['enrollment'])
                                            enrolled {{ ($row['enrollment']->enrolled_at ?? $row['enrollment']->created_at)?->format('d M Y') }}
                                        @else
                                            not enrolled
                                        @endif
                                    </p>
                                </div>
                                @if ($row['enrollment']?->completed_at)
                                    <x-mbui.badge appearance="success">Completed</x-mbui.badge>
                                @endif
                            </div>
                            <x-learning.progress-bar class="mt-2" :percent="$row['progress']['percent']"
                                :label="$row['progress']['completed'].' of '.$row['progress']['total'].' lessons'" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-mbui.card>

        <x-mbui.card class="p-0">
            <h2 class="border-b border-gray-200 px-4 py-4 text-base font-semibold text-gray-900 sm:px-6">Recent lessons</h2>
            @if ($lessons->isEmpty())
                <x-mbui.empty-state title="No lessons watched yet" />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($lessons as $row)
                        <li class="px-4 py-3 sm:px-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $row->video?->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $row->video?->course?->title ?? 'Standalone lesson' }} · {{ \App\Services\Learning\LearningAnalytics::duration($row->watched_seconds) }} watched · {{ $row->last_watched_at?->diffForHumans() ?? '—' }}</p>
                                </div>
                                @if ($row->isCompleted())
                                    <x-mbui.badge appearance="success">Completed</x-mbui.badge>
                                @endif
                            </div>
                            <x-learning.progress-bar class="mt-2" :percent="$row->percent" />
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-gray-100 px-4 py-3 sm:px-6">{{ $lessons->links() }}</div>
            @endif
        </x-mbui.card>
    </div>

    <x-mbui.card class="mt-6 p-0">
        <h2 class="border-b border-gray-200 px-4 py-4 text-base font-semibold text-gray-900 sm:px-6">Live room attendance</h2>
        @if ($attendance->isEmpty())
            <x-mbui.empty-state title="No live sessions attended" />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($attendance as $row)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">
                                {{ $row->room?->title ?? 'Deleted room' }}
                                @if ($row->room?->trashed())<span class="text-xs font-normal text-gray-400">(in trash)</span>@endif
                            </p>
                            <p class="text-xs text-gray-500">{{ ($row->first_joined_at ?? $row->session?->started_at)?->format('D, d M Y · H:i') ?? '—' }} · joined {{ $row->join_count }} {{ Str::plural('time', $row->join_count) }}</p>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            @if ($row->isHost())<x-mbui.badge appearance="info">Host</x-mbui.badge>@endif
                            @if ($row->removed_at)<x-mbui.badge appearance="danger">Removed</x-mbui.badge>@endif
                            <span class="font-medium text-gray-900">{{ \App\Services\Learning\LearningAnalytics::duration($row->total_seconds) }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-gray-100 px-4 py-3 sm:px-6">{{ $attendance->links() }}</div>
        @endif
    </x-mbui.card>
</x-layouts.admin>
