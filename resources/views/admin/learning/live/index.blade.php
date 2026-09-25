<x-layouts.admin title="Live sessions" header="Learning">
    @php
        $hms = function (int $seconds): string {
            $seconds = max(0, $seconds);

            return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        };
    @endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Live sessions</h1>
            <p class="mt-1 text-sm text-gray-500">Classes running now on our own video server, who is in them, and recent sessions.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('admin.learning.live.index', ['check' => 1])" variant="secondary">Check video server</x-mbui.btn-link>
            <x-mbui.btn-link :href="route('admin.learning.attendance.index')" variant="ghost">Attendance history</x-mbui.btn-link>
        </div>
    </div>

    {{-- Server status --}}
    <x-mbui.card class="mt-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">{{ $provider['label'] }}</h2>
                <p class="mt-0.5 truncate font-mono text-xs text-gray-500">{{ $provider['server_url'] ?? 'LIVE_SERVER_URL not set' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($provider['configured'])
                    <x-mbui.badge appearance="success">Configured</x-mbui.badge>
                @else
                    <x-mbui.badge appearance="danger">Not configured</x-mbui.badge>
                @endif
                <x-mbui.badge :appearance="$provider['turn'] ? 'success' : 'warning'">{{ $provider['turn'] ? 'TURN relay on' : 'No TURN relay' }}</x-mbui.badge>
                <x-mbui.badge :appearance="$provider['recording'] ? 'success' : 'neutral'">{{ $provider['recording'] ? 'Recording on' : 'Recording off' }}</x-mbui.badge>
                @if ($check)
                    <x-mbui.badge :appearance="$check['reachable'] ? 'success' : 'danger'">{{ $check['reachable'] ? 'Server answering' : 'Server unreachable' }}</x-mbui.badge>
                @endif
            </div>
        </div>
        @if ($check && ! $check['reachable'])
            <p class="mt-3 text-sm text-red-700">{{ $check['error'] ?? 'The video server did not answer.' }} See deploy/live-server/README.md → Troubleshooting.</p>
        @endif
        @if (count($provider['issues']) || count($provider['warnings']))
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                @foreach ([...$provider['issues'], ...$provider['warnings']] as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        @endif
    </x-mbui.card>

    {{-- Live now --}}
    <h2 class="mbui-section-label mt-8">Live now</h2>
    @if ($live->isEmpty())
        <x-learning.empty class="mt-3" title="No class is live right now" message="Rooms appear here as soon as a host starts them." />
    @else
        <x-mbui.card class="mt-3 overflow-hidden p-0">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                <div class="col-span-4">Room</div>
                <div class="col-span-2">Host</div>
                <div class="col-span-2">Started</div>
                <div class="col-span-1 text-right">Duration</div>
                <div class="col-span-1 text-right">People</div>
                <div class="col-span-2"><span class="sr-only">Actions</span></div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($live as $room)
                    @php
                        $session = $sessions->get($room->id);
                        $seconds = $room->started_at ? max(0, now()->getTimestamp() - $room->started_at->getTimestamp()) : 0;
                    @endphp
                    <li class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-4 md:grid-cols-12 md:items-center md:px-6">
                        <div class="col-span-2 min-w-0 md:col-span-4">
                            <p class="truncate font-medium text-gray-900">{{ $room->title }}</p>
                            <p class="mt-0.5 flex flex-wrap gap-1 text-xs">
                                <x-learning.room-status status="live" />
                                @if ($room->is_locked)<x-mbui.badge appearance="neutral">Locked</x-mbui.badge>@endif
                                @if ($session?->isRecording())<x-mbui.badge appearance="danger">Recording</x-mbui.badge>@endif
                            </p>
                        </div>
                        <div class="text-sm text-gray-700 md:col-span-2"><span class="block text-xs text-gray-500 md:hidden">Host</span>{{ $room->host?->name ?? '—' }}</div>
                        <div class="text-sm text-gray-700 md:col-span-2"><span class="block text-xs text-gray-500 md:hidden">Started</span>{{ $room->started_at?->format('H:i') ?? '—' }}</div>
                        <div class="text-sm tabular-nums md:col-span-1 md:text-right"><span class="block text-xs text-gray-500 md:hidden">Duration</span>{{ $hms($seconds) }}</div>
                        <div class="text-sm tabular-nums md:col-span-1 md:text-right"><span class="block text-xs text-gray-500 md:hidden">People</span>{{ number_format($session?->present_count ?? 0) }}</div>
                        <div class="col-span-2 flex flex-wrap justify-end gap-2 md:col-span-2">
                            <x-mbui.btn-link :href="route('admin.learning.live.show', $room->id)" variant="secondary" class="px-3 py-1.5 text-xs">Manage</x-mbui.btn-link>
                            <x-mbui.btn-link :href="route('learn.rooms.live', $room)" variant="ghost" class="px-3 py-1.5 text-xs">Open</x-mbui.btn-link>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-mbui.card>
    @endif

    {{-- Recent sessions --}}
    <h2 class="mbui-section-label mt-8">Recently ended</h2>
    @if ($recent->isEmpty())
        <x-learning.empty class="mt-3" title="No finished sessions yet" message="Ended sessions and their attendance show up here." />
    @else
        <x-mbui.card class="mt-3 overflow-hidden p-0">
            <ul class="divide-y divide-gray-100">
                @foreach ($recent as $session)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 md:px-6">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $session->room?->title ?? 'Deleted room' }}</p>
                            <p class="text-xs text-gray-500">{{ $session->started_at?->format('D, d M Y · H:i') }} · {{ $session->room?->host?->name ?? '—' }}</p>
                        </div>
                        <span class="text-sm tabular-nums text-gray-700">{{ $hms($session->durationSeconds()) }}</span>
                        <span class="text-sm text-gray-700">{{ number_format($session->attendances_count) }} {{ Str::plural('attendee', $session->attendances_count) }}</span>
                        @if ($session->room && ! $session->room->trashed())
                            <a href="{{ route('admin.learning.live.show', $session->room->id) }}" class="mbui-anchor text-sm">Details</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-mbui.card>
    @endif
</x-layouts.admin>
