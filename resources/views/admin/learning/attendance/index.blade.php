<x-layouts.admin title="Live attendance" header="Learning">
    @php
        $hms = function (int $seconds): string {
            $seconds = max(0, $seconds);

            return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        };
        $hasFilters = $filters['room'] || $filters['from'] || $filters['to'];
    @endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Live attendance</h1>
            <p class="mt-1 text-sm text-gray-500">Every live session across all rooms, with how many people attended and for how long.</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-mbui.stats-card title="Sessions" :value="number_format($summary['sessions'])" />
        <x-mbui.stats-card title="Attendees" :value="number_format($summary['attendees'])" trend="One per person per session" />
        <x-mbui.stats-card title="Average per session" :value="number_format($summary['per_session'], 1)" />
    </div>

    <form method="GET" action="{{ route('admin.learning.attendance.index') }}" class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        <div>
            <label for="attendance-room" class="block text-sm font-medium text-gray-700">Room</label>
            <select id="attendance-room" name="room" class="mbui-input mt-1 w-full">
                <option value="">All rooms</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" @selected($filters['room'] === $room->id)>{{ $room->title }}{{ $room->trashed() ? ' (deleted)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="attendance-from" class="block text-sm font-medium text-gray-700">From</label>
            <input id="attendance-from" type="date" name="from" value="{{ $filters['from'] }}" class="mbui-input mt-1 w-full">
            @error('from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="attendance-to" class="block text-sm font-medium text-gray-700">To</label>
            <input id="attendance-to" type="date" name="to" value="{{ $filters['to'] }}" class="mbui-input mt-1 w-full">
            @error('to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <x-mbui.button type="submit" variant="secondary">Filter</x-mbui.button>
            @if ($hasFilters)
                <x-mbui.btn-link :href="route('admin.learning.attendance.index')" variant="ghost">Clear</x-mbui.btn-link>
            @endif
        </div>
    </form>

    @if ($sessions->isEmpty())
        <x-learning.empty class="mt-4" :title="$hasFilters ? 'No sessions match these filters' : 'No live sessions yet'"
            :message="$hasFilters ? 'Try another room or a wider date range.' : 'Sessions appear here once a host starts a live room.'" />
    @else
        <x-mbui.card class="mt-4 overflow-hidden p-0">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                <div class="col-span-4">Room</div>
                <div class="col-span-3">Date</div>
                <div class="col-span-1 text-right">Duration</div>
                <div class="col-span-1 text-right">Attendees</div>
                <div class="col-span-2 text-right">Average time</div>
                <div class="col-span-1"><span class="sr-only">Actions</span></div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($sessions as $session)
                    @php $room = $session->room; @endphp
                    <li class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-4 md:grid-cols-12 md:items-center md:px-6">
                        <div class="col-span-2 min-w-0 md:col-span-4">
                            <p class="truncate font-medium text-gray-900">{{ $room?->title ?? 'Deleted room' }}</p>
                            <p class="text-xs text-gray-500">
                                @if ($session->isOpen())
                                    <span class="font-medium text-red-600">Live now</span>
                                @elseif ($room?->trashed())
                                    <span class="text-amber-700">Room in trash</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-span-2 text-sm text-gray-700 md:col-span-3">
                            <span class="block text-xs text-gray-500 md:hidden">Date</span>
                            {{ $session->started_at->format('D, d M Y · H:i') }}
                        </div>
                        <div class="text-sm md:col-span-1 md:text-right">
                            <span class="block text-xs text-gray-500 md:hidden">Duration</span>{{ $hms($session->durationSeconds()) }}
                        </div>
                        <div class="text-sm md:col-span-1 md:text-right">
                            <span class="block text-xs text-gray-500 md:hidden">Attendees</span>{{ number_format($session->attendances_count) }}
                        </div>
                        <div class="text-sm md:col-span-2 md:text-right">
                            <span class="block text-xs text-gray-500 md:hidden">Average time</span>{{ $hms((int) round((float) $session->average_seconds)) }}
                        </div>
                        <div class="col-span-2 flex justify-end md:col-span-1">
                            @if ($room && ! $room->trashed())
                                <a href="{{ route('studio.rooms.attendance', ['room' => $room->id, 'session' => $session->id]) }}" class="mbui-anchor text-sm">Details</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-mbui.card>
        <div class="mt-4">{{ $sessions->links() }}</div>
    @endif
</x-layouts.admin>
