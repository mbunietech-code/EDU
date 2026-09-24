{{--
    Session history. Expects: $room, $sessions (with attendances_count, starter), $sessionsTotal,
    $sessionImpacts, $canManage, $hms (closure).
--}}
@php($tz = (string) config('app.timezone'))

<x-mbui.card id="sessions" class="scroll-mt-20">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="mbui-section-label">Sessions</h2>
        @if ($sessionsTotal > $sessions->count())
            <span class="text-xs text-gray-500">Latest {{ $sessions->count() }} of {{ number_format($sessionsTotal) }}</span>
        @endif
    </div>

    @if ($sessions->isEmpty())
        <x-mbui.empty-state title="No sessions yet" message="Each time the room goes live a session is recorded here with its attendance." />
    @else
        {{-- md+: table --}}
        <div class="mt-3 hidden overflow-hidden rounded-lg border border-gray-200 md:block">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="mbui-th">Started</th>
                        <th scope="col" class="mbui-th">Ended</th>
                        <th scope="col" class="mbui-th">Duration</th>
                        <th scope="col" class="mbui-th text-right">Peak</th>
                        <th scope="col" class="mbui-th text-right">Attendees</th>
                        <th scope="col" class="mbui-th text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($sessions as $session)
                        <tr>
                            <td class="mbui-td whitespace-nowrap text-sm text-gray-700">
                                {{ $session->started_at?->copy()->setTimezone($tz)->format('D, d M Y · H:i') ?? '—' }}
                                @if ($session->starter)<span class="block text-xs text-gray-500">by {{ $session->starter->name }}</span>@endif
                            </td>
                            <td class="mbui-td whitespace-nowrap text-sm text-gray-700">
                                @if ($session->ended_at)
                                    {{ $session->ended_at->copy()->setTimezone($tz)->format('H:i') }}
                                @else
                                    <x-mbui.badge appearance="danger">Running</x-mbui.badge>
                                @endif
                            </td>
                            <td class="mbui-td text-sm tabular-nums text-gray-700">{{ $hms($session->durationSeconds()) }}</td>
                            <td class="mbui-td text-right text-sm tabular-nums text-gray-700">{{ (int) $session->peak_participants }}</td>
                            <td class="mbui-td text-right text-sm tabular-nums">
                                <a href="{{ route('studio.rooms.attendance', ['room' => $room, 'session' => $session->id]) }}" class="mbui-anchor">{{ (int) $session->attendances_count }}</a>
                            </td>
                            <td class="mbui-td text-right">
                                @if ($canManage && $session->ended_at)
                                    <x-learning.confirm-delete :action="route('studio.rooms.sessions.destroy', [$room, $session])"
                                        title="Delete this session record?" :impact="$sessionImpacts[$session->id] ?? []"
                                        :button-label="'Delete session of '.$session->started_at?->copy()->setTimezone($tz)->format('d M Y H:i')" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- mobile: stacked --}}
        <ul class="mt-3 divide-y divide-gray-100 md:hidden">
            @foreach ($sessions as $session)
                <li class="flex items-start justify-between gap-3 py-3">
                    <div class="min-w-0 text-sm">
                        <p class="font-medium text-gray-900">{{ $session->started_at?->copy()->setTimezone($tz)->format('d M Y · H:i') ?? '—' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $session->ended_at ? $hms($session->durationSeconds()) : 'Running' }}
                            · peak {{ (int) $session->peak_participants }}
                            · <a href="{{ route('studio.rooms.attendance', ['room' => $room, 'session' => $session->id]) }}" class="mbui-anchor">{{ (int) $session->attendances_count }} attendees</a>
                        </p>
                    </div>
                    @if ($canManage && $session->ended_at)
                        <x-learning.confirm-delete :action="route('studio.rooms.sessions.destroy', [$room, $session])"
                            title="Delete this session record?" :impact="$sessionImpacts[$session->id] ?? []"
                            button-label="Delete session record" />
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-mbui.card>
