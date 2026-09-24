<x-layouts.app :title="'Participants · '.$room->title" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>
    @php
        $tz = (string) config('app.timezone');
        $hms = fn (int $s) => sprintf('%d:%02d:%02d', intdiv(max(0, $s), 3600), intdiv(max(0, $s) % 3600, 60), max(0, $s) % 60);
    @endphp

    <div class="mbui-page-header">
        <div class="min-w-0">
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.rooms.index') }}" class="mbui-anchor">Live rooms</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('studio.rooms.show', $room) }}" class="mbui-anchor">{{ \Illuminate\Support\Str::limit($room->title, 40) }}</a>
                <span aria-hidden="true">/</span>
                <span>Participants</span>
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="mbui-title">Participants</h1>
                <x-learning.room-status :status="$room->status" />
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ $room->accessLabel() }} · hosted by {{ $room->host->name ?? 'nobody' }}</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('studio.rooms.attendance', $room)" variant="secondary">Attendance report</x-mbui.btn-link>
        </div>
    </div>

    @if ($errors->has('user') || $errors->has('status'))
        <x-mbui.alert type="error" class="mt-4" role="alert">{{ $errors->first('user') ?: $errors->first('status') }}</x-mbui.alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- In the call now --}}
            <x-mbui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="mbui-section-label">In the call now</h2>
                    @if ($room->isLive())
                        <span class="text-sm font-medium text-gray-700">{{ $present->count() }} present</span>
                    @endif
                </div>

                @if (! $room->isLive())
                    <x-mbui.empty-state title="The room is not live" message="People in the call appear here while a session is running." />
                @elseif ($present->isEmpty())
                    <x-mbui.empty-state title="Nobody is in the call yet" message="Participants appear here within a few seconds of joining." />
                @else
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($present as $attendance)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700" aria-hidden="true">
                                        {{ mb_strtoupper(mb_substr($attendance->user->name ?? '?', 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">
                                            {{ $attendance->user->name ?? 'Member' }}
                                            @if ($attendance->role === 'host')<x-mbui.badge appearance="info" class="ml-1">Host</x-mbui.badge>@endif
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Joined {{ $attendance->first_joined_at?->copy()->setTimezone($tz)->format('H:i') }}
                                            · {{ $hms((int) $attendance->total_seconds) }} in the call
                                        </p>
                                    </div>
                                </div>
                                @if ($canManage && $attendance->role !== 'host' && (int) $attendance->user_id !== (int) auth()->id())
                                    <div x-data="{ open: false }" @keydown.escape.window="open = false">
                                        <button type="button" @click="open = true" class="rounded-md px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
                                            aria-label="Remove {{ $attendance->user->name ?? 'this participant' }} from the session">Remove</button>
                                        <template x-teleport="body">
                                            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-label="Remove participant">
                                                <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="open = false" aria-hidden="true"></div>
                                                <form x-show="open" x-transition method="POST" action="{{ route('studio.rooms.participants.remove', [$room, $attendance->user_id]) }}"
                                                    class="relative w-full max-w-md rounded-xl bg-white p-6 text-left shadow-xl">
                                                    @csrf
                                                    <h2 class="text-base font-semibold text-gray-900">Remove {{ $attendance->user->name ?? 'this participant' }}?</h2>
                                                    <p class="mt-2 text-sm text-gray-600">They are disconnected within a few seconds and cannot rejoin this session. They can join future sessions.</p>
                                                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                        <x-mbui.button variant="secondary" @click="open = false">Keep</x-mbui.button>
                                                        <x-mbui.button type="submit" variant="danger">Remove</x-mbui.button>
                                                    </div>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-mbui.card>

            {{-- Invited members --}}
            <x-mbui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="mbui-section-label">Invited members</h2>
                    <span class="text-sm text-gray-500">{{ number_format((int) $room->members_count) }}</span>
                </div>

                @if ($room->access !== 'private')
                    <p class="mt-2 text-sm text-gray-600">
                        This room is open to <span class="font-medium">{{ strtolower($room->accessLabel()) }}</span>, so there is no invitation list.
                        @if ($canManage)
                            <a href="{{ route('studio.rooms.edit', $room) }}" class="mbui-anchor">Make it private</a> to invite specific people.
                        @endif
                    </p>
                @else
                    @if ($canManage)
                        <form method="POST" action="{{ route('studio.rooms.members.store', $room) }}" class="mt-3 space-y-3">
                            @csrf
                            <label for="add-members" class="mbui-label">Invite more members</label>
                            <div x-data="learnUserPicker(@js($memberPicker))"></div>
                            @error('user_ids')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                            @error('user_ids.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                            <div class="flex justify-end">
                                <x-mbui.button type="submit" class="w-full sm:w-auto">Invite</x-mbui.button>
                            </div>
                        </form>
                    @endif

                    @if ($members->isEmpty())
                        <x-mbui.empty-state title="Nobody is invited yet" message="Only invited members (and room staff) can see and join a private room." />
                    @else
                        <ul class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                            @foreach ($members as $member)
                                <li class="flex items-center justify-between gap-3 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">
                                            {{ $member->user->name ?? 'Former member' }}
                                            @if ($member->user && $member->user->status !== 'active')<x-mbui.badge appearance="warning" class="ml-1">Inactive</x-mbui.badge>@endif
                                        </p>
                                        <p class="truncate text-xs text-gray-500">
                                            @if ($showEmail && $member->user){{ $member->user->email }} · @endif
                                            invited {{ $member->created_at?->copy()->setTimezone($tz)->format('d M Y') }}{{ $member->adder ? ' by '.$member->adder->name : '' }}
                                        </p>
                                    </div>
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('studio.rooms.members.destroy', [$room, $member]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                title="Remove invitation" aria-label="Remove the invitation of {{ $member->user->name ?? 'this member' }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>
                                            </button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3">{{ $members->links() }}</div>
                    @endif
                @endif
            </x-mbui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-mbui.card>
                <h2 class="mbui-section-label">{{ $room->isLive() ? 'This session' : 'Last session' }}</h2>
                @if (! $summary)
                    <p class="mt-3 text-sm text-gray-500">No session has been held yet.</p>
                @else
                    <p class="mt-2 text-xs text-gray-500">{{ $summary['session']->started_at?->copy()->setTimezone($tz)->format('D, d M Y · H:i') }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs text-gray-500">Attended</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($summary['attendees']) }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs text-gray-500">{{ $room->isLive() ? 'Present now' : 'Peak' }}</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($room->isLive() ? $summary['present'] : (int) $summary['session']->peak_participants) }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs text-gray-500">Average time</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ $hms($summary['attendees'] > 0 ? intdiv($summary['total_seconds'], $summary['attendees']) : 0) }}</dd>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3">
                            <dt class="text-xs text-gray-500">Removed</dt>
                            <dd class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ number_format($summary['removed']) }}</dd>
                        </div>
                    </dl>
                    <a href="{{ route('studio.rooms.attendance', ['room' => $room, 'session' => $summary['session']->id]) }}" class="mbui-anchor mt-4 inline-block text-sm">Full attendance →</a>
                @endif
            </x-mbui.card>
        </div>
    </div>
</x-layouts.app>
