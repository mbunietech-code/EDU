<x-layouts.app title="Live rooms" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>
    @php
        $tz = (string) config('app.timezone');
        $participantsLabel = function (\App\Models\LearningRoom $room) use ($participants) {
            $p = $participants[$room->id] ?? ['present' => null, 'attended' => null, 'invited' => null];
            $parts = [];
            if ($p['present'] !== null) {
                $parts[] = [$p['present'].' present now', 'text-red-700 font-medium'];
            } elseif ($p['attended'] !== null) {
                $parts[] = [$p['attended'].' attended', 'text-gray-700'];
            }
            if ($p['invited'] !== null) {
                $parts[] = [$p['invited'].' invited', 'text-gray-500'];
            }
            return $parts;
        };
    @endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Live rooms</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $seesAll ? 'Every live classroom on the platform.' : 'The live classes you host.' }}
                Schedule sessions, go live, and review attendance and recordings.
            </p>
        </div>
        @if ($canCreate)
            <div class="flex gap-2">
                <x-mbui.btn-link :href="route('studio.rooms.create')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Create room
                </x-mbui.btn-link>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="mt-6 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-1 border-b border-gray-200 text-sm" role="tablist" aria-label="Room status">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('studio.rooms.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => $search ?: null])) }}"
                    role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    class="whitespace-nowrap border-b-2 px-3 py-2 font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                    <span class="ml-1 rounded-full px-1.5 text-xs {{ $key === 'live' && $counts['live'] > 0 ? 'bg-red-600 text-white' : ($tab === $key ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600') }}">{{ number_format($counts[$key]) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('studio.rooms.index') }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
        @if ($tab !== 'all')
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif
        <label for="rooms-q" class="sr-only">Search rooms</label>
        <input id="rooms-q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search by title…" class="mbui-input w-full sm:max-w-sm">
        <div class="flex gap-2">
            <x-mbui.button type="submit" variant="secondary" class="flex-1 sm:flex-none">Search</x-mbui.button>
            @if ($search !== '')
                <x-mbui.btn-link :href="route('studio.rooms.index', $tab !== 'all' ? ['tab' => $tab] : [])" variant="ghost" class="flex-1 sm:flex-none">Clear</x-mbui.btn-link>
            @endif
        </div>
    </form>

    @if ($rooms->isEmpty())
        <div class="mt-4">
            @if ($counts['all'] === 0 && $search === '')
                <x-learning.empty title="No live rooms yet"
                    :message="$canCreate ? 'Create a room to host a live class with video, chat and questions — learners join from their browser.' : 'Rooms appear here once hosts create them.'"
                    :action-href="$canCreate ? route('studio.rooms.create') : null" :action-label="$canCreate ? 'Create room' : null" />
            @else
                <x-learning.empty title="No rooms here"
                    :message="$search !== '' ? 'No room title matches “'.$search.'”. Try another search.' : 'There are no rooms in this tab.'"
                    :action-href="$search !== '' ? route('studio.rooms.index', $tab !== 'all' ? ['tab' => $tab] : []) : null"
                    :action-label="$search !== '' ? 'Clear search' : null" />
            @endif
        </div>
    @else
        {{-- Desktop table --}}
        <div class="mbui-card mt-4 hidden overflow-hidden md:block">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="mbui-th">Room</th>
                        <th scope="col" class="mbui-th hidden lg:table-cell">Category</th>
                        <th scope="col" class="mbui-th hidden xl:table-cell">Instructor</th>
                        <th scope="col" class="mbui-th">Date</th>
                        <th scope="col" class="mbui-th hidden lg:table-cell">Time</th>
                        <th scope="col" class="mbui-th">Status</th>
                        <th scope="col" class="mbui-th">Participants</th>
                        <th scope="col" class="mbui-th text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($rooms as $room)
                        @php($start = $room->scheduled_at?->copy()->setTimezone($tz))
                        <tr class="{{ $room->isLive() ? 'bg-red-50/40' : '' }} hover:bg-gray-50">
                            <td class="mbui-td">
                                <a href="{{ route('studio.rooms.show', $room) }}" class="line-clamp-2 font-medium text-gray-900 hover:text-indigo-600">{{ $room->title }}</a>
                                <p class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ $room->accessLabel() }}
                                    @if ($room->course) · {{ $room->course->title }}@if ($room->course->trashed()) <span class="text-red-600">(in trash)</span>@endif @endif
                                    <span class="xl:hidden"> · {{ $room->host->name ?? 'No host' }}</span>
                                </p>
                            </td>
                            <td class="mbui-td hidden text-sm text-gray-600 lg:table-cell">{{ $room->category->name ?? '—' }}</td>
                            <td class="mbui-td hidden text-sm text-gray-600 xl:table-cell">{{ $room->host->name ?? '—' }}</td>
                            <td class="mbui-td whitespace-nowrap text-sm text-gray-700">
                                {{ $start ? $start->format('D, d M Y') : 'Not set' }}
                                <span class="block text-xs text-gray-500 lg:hidden">{{ $start ? $start->format('H:i') : '' }}</span>
                            </td>
                            <td class="mbui-td hidden whitespace-nowrap text-sm text-gray-700 lg:table-cell">
                                @if ($start)
                                    {{ $start->format('H:i') }}–{{ $room->endsAt()?->setTimezone($tz)->format('H:i') }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="mbui-td"><x-learning.room-status :status="$room->status" /></td>
                            <td class="mbui-td text-sm">
                                @forelse ($participantsLabel($room) as [$text, $cls])
                                    <span class="block {{ $cls }}">{{ $text }}</span>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="mbui-td">
                                @include('studio.rooms.partials.row-actions', ['room' => $room, 'impact' => $impacts[$room->id] ?? []])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <ul class="mt-4 space-y-3 md:hidden">
            @foreach ($rooms as $room)
                @php($start = $room->scheduled_at?->copy()->setTimezone($tz))
                <li class="mbui-card overflow-hidden {{ $room->isLive() ? 'ring-1 ring-red-200' : '' }}">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('studio.rooms.show', $room) }}" class="line-clamp-2 min-w-0 text-sm font-medium text-gray-900">{{ $room->title }}</a>
                            <x-learning.room-status :status="$room->status" class="shrink-0" />
                        </div>
                        <p class="mt-1 truncate text-xs text-gray-500">
                            {{ $room->category->name ?? 'No category' }} · {{ $room->host->name ?? 'No host' }}
                        </p>
                    </div>
                    <dl class="grid grid-cols-3 gap-2 border-t border-gray-100 px-4 py-3 text-xs">
                        <div>
                            <dt class="text-gray-500">Date</dt>
                            <dd class="font-medium text-gray-900">{{ $start ? $start->format('d M Y') : 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Time</dt>
                            <dd class="font-medium text-gray-900">{{ $start ? $start->format('H:i') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Participants</dt>
                            <dd class="font-medium text-gray-900">
                                @forelse ($participantsLabel($room) as [$text, $cls])
                                    <span class="block {{ $cls }}">{{ $text }}</span>
                                @empty
                                    —
                                @endforelse
                            </dd>
                        </div>
                    </dl>
                    <div class="border-t border-gray-100 bg-gray-50 px-4 py-2">
                        @include('studio.rooms.partials.row-actions', ['room' => $room, 'impact' => $impacts[$room->id] ?? [], 'mobile' => true])
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $rooms->links() }}</div>
    @endif
</x-layouts.app>
