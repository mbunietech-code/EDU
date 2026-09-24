{{-- Actions for one room in the studio list. Expects: $room, $impact (list<string>), optional $mobile. --}}
@php
    $mobile = $mobile ?? false;
    $canManage = auth()->user()->can('manage', $room);
    $iconCls = 'inline-flex rounded p-1.5 text-gray-400 hover:bg-indigo-50 hover:text-indigo-600';
@endphp

<div class="flex flex-wrap items-center {{ $mobile ? 'justify-between' : 'justify-end' }} gap-1">
    <div class="flex items-center gap-1">
        <x-mbui.icon-link :href="route('studio.rooms.show', $room)" icon="eye" :label="'View “'.$room->title.'”'" />

        @if ($canManage)
            <x-mbui.icon-link :href="route('studio.rooms.edit', $room)" icon="pencil" :label="'Edit “'.$room->title.'”'" />
        @endif

        <a href="{{ route('studio.rooms.participants', $room) }}" title="Participants" class="{{ $iconCls }}">
            <span class="sr-only">Participants of “{{ $room->title }}”</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
        </a>

        <a href="{{ route('studio.rooms.attendance', $room) }}" title="Attendance" class="{{ $iconCls }}">
            <span class="sr-only">Attendance of “{{ $room->title }}”</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>
        </a>

        <a href="{{ route('studio.rooms.show', $room) }}#recordings" title="Recordings" class="{{ $iconCls }}">
            <span class="sr-only">Recordings of “{{ $room->title }}”</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>
        </a>
    </div>

    <div class="flex items-center gap-1">
        @if ($canManage)
            @if ($room->isLive())
                <a href="{{ route('learn.rooms.live', $room) }}" class="rounded-md px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                    aria-label="Open the live room “{{ $room->title }}”">Open</a>
                @include('studio.rooms.partials.end-confirm', ['room' => $room, 'compact' => true])
            @elseif (in_array($room->status, ['draft', 'scheduled', 'completed'], true))
                <form method="POST" action="{{ route('studio.rooms.start', $room) }}">
                    @csrf
                    <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                        aria-label="Start a live session in “{{ $room->title }}”"
                        title="{{ $room->status === 'completed' ? 'Start a new session' : 'Go live now' }}">Start</button>
                </form>
            @endif

            @if ($room->isLive())
                <span class="inline-flex cursor-not-allowed rounded p-1.5 text-gray-300" title="End the live session before deleting the room">
                    <span class="sr-only">Delete unavailable while the room is live</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </span>
            @else
                <x-learning.confirm-delete :action="route('studio.rooms.destroy', $room)"
                    :title="'Delete “'.$room->title.'”?'" :impact="$impact"
                    :button-label="'Delete “'.$room->title.'”'" />
            @endif
        @endif
    </div>
</div>
