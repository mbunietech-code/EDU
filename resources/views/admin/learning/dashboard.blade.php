@php
    $me = auth()->user();
    $roomLinks = $me->can('rooms.view');
    $lessonEdit = $me->can('learning.manage');
    $generated = \Illuminate\Support\Carbon::parse($stats['generated_at']);
@endphp
<x-layouts.admin title="Learning overview" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Learning overview</h1>
            <p class="mt-1 text-sm text-gray-500">Lessons, courses, live rooms and learner activity at a glance. Figures refresh every 5 minutes (last {{ $generated->diffForHumans() }}).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->canHostRooms())
                <x-mbui.btn-link :href="route('studio.rooms.create')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Create room
                </x-mbui.btn-link>
            @endif
            @if (auth()->user()->canUploadLessons())
                <x-mbui.btn-link :href="route('studio.videos.create')" variant="secondary">Add video</x-mbui.btn-link>
            @endif
            @can('learning.manage')
                <x-mbui.btn-link :href="route('admin.learning.courses.create')" variant="secondary">New course</x-mbui.btn-link>
            @endcan
            @can('learning.trash')
                <x-mbui.btn-link :href="route('admin.learning.trash.index')" variant="ghost">Trash</x-mbui.btn-link>
            @endcan
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-mbui.stats-card title="Video lessons" :value="number_format($stats['videos'])" :trend="number_format($stats['videos_published']).' published'">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0118 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0118 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 016 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M19.125 12h1.5m0 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h1.5m14.25 0h1.5" /></svg>
            </x-slot:icon>
        </x-mbui.stats-card>
        <x-mbui.stats-card title="Courses" :value="number_format($stats['courses'])" :trend="number_format($stats['courses_published']).' published · '.number_format($stats['categories']).' categories'">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
            </x-slot:icon>
        </x-mbui.stats-card>
        <x-mbui.stats-card title="Live rooms" :value="number_format($stats['live_now']).' live now'" :trend="number_format($stats['upcoming']).' upcoming'">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>
            </x-slot:icon>
        </x-mbui.stats-card>
        <x-mbui.stats-card title="Learners" :value="number_format($stats['learners'])" trend="Members with progress or an enrolment">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" /></svg>
            </x-slot:icon>
        </x-mbui.stats-card>
        <x-mbui.stats-card title="Video views" :value="number_format($stats['video_views'])" :trend="number_format($stats['completed_lessons']).' lessons completed'" />
        <x-mbui.stats-card title="Average completion" :value="$stats['average_completion'].'%'" trend="Mean watched % across all lesson progress" />
        <x-mbui.stats-card title="Live attendance (30 days)" :value="number_format($stats['attendance_30d'])" :trend="number_format($stats['sessions_30d']).' sessions · '.$stats['attendance_per_session'].' per session'" />
        <x-mbui.stats-card title="Live video server" :value="$provider['configured'] ? 'Self-hosted' : 'Not set up'" :trend="$provider['configured'] ? 'Configured' : 'Needs attention'" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Most watched lessons --}}
        <x-mbui.card class="p-0 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-gray-900">Most watched lessons</h2>
                @if (auth()->user()->canAccessStudio())
                    <a href="{{ route('studio.videos.index') }}" class="mbui-anchor text-sm">All lessons</a>
                @endif
            </div>
            @if (count($stats['top_lessons']))
                <ol class="divide-y divide-gray-100">
                    @foreach ($stats['top_lessons'] as $i => $lesson)
                        <li class="flex items-center gap-3 px-4 py-3 sm:px-6">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $lessonEdit ? route('studio.videos.edit', $lesson['id']) : route('learn.videos.show', $lesson['slug']) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $lesson['title'] }}</a>
                                <p class="truncate text-xs text-gray-500">{{ $lesson['course'] ?? 'Standalone lesson' }}</p>
                            </div>
                            <div class="shrink-0 text-right text-xs text-gray-500">
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($lesson['views']) }} <span class="font-normal text-gray-500">views</span></p>
                                <p>{{ number_format($lesson['completions']) }} completed</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-mbui.empty-state title="No views yet" message="Lessons appear here once learners start watching." />
            @endif
        </x-mbui.card>

        {{-- Live video server health (self-hosted LiveKit SFU + coturn) --}}
        <x-mbui.card>
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-semibold text-gray-900">Live video server</h2>
                @if ($provider['configured'] && ! count($provider['warnings']))
                    <x-mbui.badge appearance="success">Ready</x-mbui.badge>
                @elseif ($provider['configured'])
                    <x-mbui.badge appearance="warning">Check setup</x-mbui.badge>
                @else
                    <x-mbui.badge appearance="danger">Not configured</x-mbui.badge>
                @endif
            </div>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Server</dt><dd class="text-right font-medium text-gray-900">{{ $provider['label'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Address</dt><dd class="truncate text-right font-mono text-xs text-gray-900">{{ $provider['server_url'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">TURN relay</dt><dd class="text-right text-gray-900">{{ $provider['turn'] ? 'Configured' : 'Not configured' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Server recording</dt><dd class="text-right text-gray-900">{{ $provider['recording'] ? 'Available' : 'Not enabled' }}</dd></div>
            </dl>
            @if (count($provider['issues']) || count($provider['warnings']))
                <ul class="mt-4 space-y-2 text-sm text-gray-700">
                    @foreach ([...$provider['issues'], ...$provider['warnings']] as $issue)
                        <li class="flex gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                            <span>{{ $issue }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-emerald-700">No configuration issues found.</p>
            @endif
            @can('rooms.view')
                <a href="{{ route('admin.learning.live.index') }}" class="mbui-anchor mt-4 inline-block text-sm">Live sessions &amp; server check &rarr;</a>
            @endcan
        </x-mbui.card>

        {{-- Most active courses --}}
        <x-mbui.card class="p-0 lg:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-gray-900">Most active courses <span class="text-sm font-normal text-gray-500">(last 30 days)</span></h2>
                <a href="{{ route('admin.learning.progress.index', ['tab' => 'courses']) }}" class="mbui-anchor text-sm">Course progress</a>
            </div>
            @if (count($stats['active_courses']))
                <ul class="divide-y divide-gray-100">
                    @foreach ($stats['active_courses'] as $course)
                        <li class="flex items-center gap-3 px-4 py-3 sm:px-6">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.learning.courses.show', $course['id']) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $course['title'] }}</a>
                                <p class="truncate text-xs text-gray-500">{{ $course['category'] ?? '—' }}</p>
                            </div>
                            <x-mbui.status-badge :status="$course['status']" />
                            <div class="w-28 shrink-0 text-right text-xs text-gray-500">
                                <p class="text-sm font-semibold text-gray-900">{{ number_format($course['learners']) }} <span class="font-normal text-gray-500">{{ Str::plural('learner', $course['learners']) }}</span></p>
                                <p>{{ number_format($course['activity']) }} lesson {{ Str::plural('session', $course['activity']) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-mbui.empty-state title="No recent course activity" message="Courses show up here when learners watch their lessons." />
            @endif
        </x-mbui.card>

        {{-- Upcoming + live --}}
        <x-mbui.card class="p-0">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">
                <h2 class="text-base font-semibold text-gray-900">Live &amp; upcoming</h2>
                @if (auth()->user()->canAccessStudio())
                    <a href="{{ route('studio.rooms.index') }}" class="mbui-anchor text-sm">All rooms</a>
                @endif
            </div>
            @if ($liveRooms->isEmpty() && ! count($stats['upcoming_sessions']))
                <x-mbui.empty-state title="Nothing scheduled" message="Scheduled live sessions will be listed here." />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($liveRooms as $room)
                        <li class="px-4 py-3 sm:px-6">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ $roomLinks ? route('studio.rooms.show', $room) : route('learn.rooms.show', $room->slug) }}" class="truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $room->title }}</a>
                                <x-learning.room-status status="live" />
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $room->host?->name ?? 'No host' }} · {{ $room->present_count }} in the room now</p>
                        </li>
                    @endforeach
                    @foreach ($stats['upcoming_sessions'] as $session)
                        <li class="px-4 py-3 sm:px-6">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ $roomLinks ? route('studio.rooms.show', $session['id']) : route('learn.rooms.show', $session['slug']) }}" class="truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $session['title'] }}</a>
                                <x-learning.room-status :status="$session['status']" />
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500">
                                @if ($session['scheduled_at'])
                                    {{ \Illuminate\Support\Carbon::parse($session['scheduled_at'])->timezone(config('app.timezone'))->format('D, d M Y · H:i') }} ·
                                @endif
                                {{ $session['host'] ?? 'No host' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-mbui.card>
    </div>

    {{-- Quick links --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @php
            $links = array_filter([
                ['Courses', route('admin.learning.courses.index'), true],
                ['Categories', route('admin.learning.categories.index'), true],
                ['Instructors', route('admin.learning.instructors.index'), true],
                ['Learner progress', route('admin.learning.progress.index'), true],
                ['Attendance', route('admin.learning.attendance.index'), auth()->user()->can('rooms.view')],
                ['Trash', route('admin.learning.trash.index'), auth()->user()->can('learning.trash')],
            ], fn ($l) => $l[2]);
        @endphp
        @foreach ($links as [$label, $href])
            <a href="{{ $href }}" class="mbui-card flex items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 hover:text-indigo-600">
                {{ $label }}
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
