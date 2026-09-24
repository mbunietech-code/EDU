<x-layouts.app :title="'Attendance · '.$room->title" header="Teaching Studio">

    @php
        $tz = (string) config('app.timezone');
        $hms = fn (int $s) => sprintf('%d:%02d:%02d', intdiv(max(0, $s), 3600), intdiv(max(0, $s) % 3600, 60), max(0, $s) % 60);
        $fmt = fn ($at, string $format = 'H:i:s') => $at ? $at->copy()->setTimezone($tz)->format($format) : '—';
    @endphp

    <div class="mbui-page-header">
        <div class="min-w-0">
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.rooms.index') }}" class="mbui-anchor">Live rooms</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('studio.rooms.show', $room) }}" class="mbui-anchor">{{ \Illuminate\Support\Str::limit($room->title, 40) }}</a>
                <span aria-hidden="true">/</span>
                <span>Attendance</span>
            </nav>
            <h1 class="mbui-title">Attendance</h1>
            <p class="mt-1 text-sm text-gray-500">Who joined each session and for how long. Time counts only while someone’s page is open in the call.</p>
        </div>
        @if ($session)
            <div class="flex gap-2">
                <x-mbui.btn-link :href="route('studio.rooms.attendance.export', ['room' => $room, 'session' => $session->id])" variant="secondary">
                    <svg class="-ml-0.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </x-mbui.btn-link>
            </div>
        @endif
    </div>

    @if ($sessions->isEmpty())
        <div class="mt-6">
            <x-learning.empty title="No sessions yet" message="Attendance is recorded automatically each time the room goes live." />
        </div>
    @else
        <form method="GET" action="{{ route('studio.rooms.attendance', $room) }}" class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="w-full sm:max-w-sm">
                <label for="attendance-session" class="mbui-label">Session</label>
                <select id="attendance-session" name="session" class="mbui-input mt-1 w-full" onchange="this.form.submit()">
                    @foreach ($sessions as $s)
                        <option value="{{ $s->id }}" @selected($session && $session->id === $s->id)>
                            {{ $fmt($s->started_at, 'D, d M Y · H:i') }}{{ $s->ended_at ? '' : ' (running)' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <noscript><x-mbui.button type="submit" variant="secondary">Show</x-mbui.button></noscript>
        </form>

        {{-- Totals --}}
        <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['Attendees', number_format($totals['attendees'])],
                ['Learners', number_format($totals['participants'])],
                ['Peak at once', number_format($totals['peak'])],
                ['Average time', $hms($totals['average_seconds'])],
                ['Total time', $hms($totals['total_seconds'])],
                ['Session length', $hms($totals['duration_seconds'])],
            ] as [$label, $value])
                <div class="mbui-card p-4">
                    <dt class="text-xs text-gray-500">{{ $label }}</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-900">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($totals['removed'] > 0)
            <p class="mt-2 text-sm text-gray-600">{{ $totals['removed'] }} {{ $totals['removed'] === 1 ? 'person was' : 'people were' }} removed from this session.</p>
        @endif

        @if ($rows->isEmpty())
            <div class="mt-4">
                <x-learning.empty title="Nobody joined this session" message="When people join the call, their time is tracked here." />
            </div>
        @else
            {{-- md+: table --}}
            <div class="mbui-card mt-4 hidden overflow-hidden md:block">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="mbui-th">Member</th>
                            <th scope="col" class="mbui-th">Role</th>
                            <th scope="col" class="mbui-th">First joined</th>
                            <th scope="col" class="mbui-th">Last seen / left</th>
                            <th scope="col" class="mbui-th text-right">Total time</th>
                            <th scope="col" class="mbui-th text-right">Joins</th>
                            <th scope="col" class="mbui-th">Removed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($rows as $a)
                            <tr>
                                <td class="mbui-td">
                                    <p class="text-sm font-medium text-gray-900">{{ $a->user->name ?? 'Former member' }}</p>
                                    @if ($showEmail && $a->user)<p class="text-xs text-gray-500">{{ $a->user->email }}</p>@endif
                                </td>
                                <td class="mbui-td">
                                    <x-mbui.badge :appearance="$a->role === 'host' ? 'info' : 'neutral'">{{ ucfirst($a->role) }}</x-mbui.badge>
                                </td>
                                <td class="mbui-td whitespace-nowrap text-sm text-gray-700">{{ $fmt($a->first_joined_at) }}</td>
                                <td class="mbui-td whitespace-nowrap text-sm text-gray-700">
                                    @if ($a->isPresent())
                                        <span class="inline-flex items-center gap-1 text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>In the call</span>
                                    @elseif ($a->left_at)
                                        Left {{ $fmt($a->left_at) }}
                                    @else
                                        Seen {{ $fmt($a->last_seen_at) }}
                                    @endif
                                </td>
                                <td class="mbui-td text-right text-sm tabular-nums text-gray-900">{{ $hms((int) $a->total_seconds) }}</td>
                                <td class="mbui-td text-right text-sm tabular-nums text-gray-700">{{ (int) $a->join_count }}</td>
                                <td class="mbui-td text-sm">
                                    @if ($a->removed_at)
                                        <span class="text-red-700">{{ $fmt($a->removed_at, 'H:i') }}</span>
                                        @if ($a->remover)<span class="block text-xs text-gray-500">by {{ $a->remover->name }}</span>@endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- mobile: cards --}}
            <ul class="mt-4 space-y-3 md:hidden">
                @foreach ($rows as $a)
                    <li class="mbui-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $a->user->name ?? 'Former member' }}</p>
                                @if ($showEmail && $a->user)<p class="truncate text-xs text-gray-500">{{ $a->user->email }}</p>@endif
                            </div>
                            <x-mbui.badge :appearance="$a->role === 'host' ? 'info' : 'neutral'" class="shrink-0">{{ ucfirst($a->role) }}</x-mbui.badge>
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div><dt class="text-gray-500">Joined</dt><dd class="font-medium text-gray-900">{{ $fmt($a->first_joined_at, 'H:i') }}</dd></div>
                            <div><dt class="text-gray-500">Time</dt><dd class="font-medium tabular-nums text-gray-900">{{ $hms((int) $a->total_seconds) }}</dd></div>
                            <div><dt class="text-gray-500">Joins</dt><dd class="font-medium text-gray-900">{{ (int) $a->join_count }}</dd></div>
                        </dl>
                        <p class="mt-2 text-xs text-gray-500">
                            @if ($a->isPresent()) In the call now @elseif ($a->left_at) Left {{ $fmt($a->left_at, 'H:i') }} @else Seen {{ $fmt($a->last_seen_at, 'H:i') }} @endif
                            @if ($a->removed_at) · <span class="text-red-700">removed {{ $fmt($a->removed_at, 'H:i') }}</span>@endif
                        </p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    @endif
</x-layouts.app>
