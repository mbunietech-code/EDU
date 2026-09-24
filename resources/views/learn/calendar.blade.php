<x-layouts.app title="Live Class Calendar" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Live class calendar</h1>
            <p class="mt-1 text-sm text-gray-500">Scheduled, live and past sessions you can attend.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.rooms.index')" variant="secondary">All live rooms</x-mbui.btn-link>
        </div>
    </div>

    {{-- Month navigation --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900" id="calendar-month">{{ $month->format('F Y') }}</h2>
        <nav class="flex items-center gap-2" aria-label="Change month">
            <a href="{{ route('learn.calendar', ['month' => $prevMonth]) }}" rel="prev"
                class="rounded-lg bg-white p-2 text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" aria-label="Previous month">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
            <x-mbui.btn-link :href="route('learn.calendar')" variant="secondary" :aria-current="$isCurrentMonth ? 'date' : null">Today</x-mbui.btn-link>
            <a href="{{ route('learn.calendar', ['month' => $nextMonth]) }}" rel="next"
                class="rounded-lg bg-white p-2 text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" aria-label="Next month">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </a>
        </nav>
    </div>

    {{-- Legend --}}
    <ul class="mt-3 flex flex-wrap gap-2 text-xs" aria-label="Legend">
        @foreach (['live' => 'Live', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
            <li class="inline-flex items-center rounded-md px-2 py-0.5 ring-1 ring-inset {{ $statusStyles[$key] }}">{{ $label }}</li>
        @endforeach
    </ul>

    @if ($truncated)
        <x-mbui.alert type="warning" class="mt-4">Only the first {{ $rooms->count() }} sessions of this month are shown.</x-mbui.alert>
    @endif

    @if ($rooms->isEmpty())
        <x-learning.empty class="mt-4" title="No classes in {{ $month->format('F Y') }}."
            message="Scheduled live sessions you can attend will show up on this calendar."
            :action-href="route('learn.rooms.index', ['tab' => 'upcoming'])" action-label="See upcoming classes" />
    @endif

    {{-- Month grid (md+) --}}
    <div class="mbui-card mt-4 hidden overflow-hidden md:block" aria-labelledby="calendar-month">
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500" aria-hidden="true">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                <div class="py-2">{{ $dow }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7" role="grid" aria-label="{{ $month->format('F Y') }}">
            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    @php
                        $key = $day->format('Y-m-d');
                        $inMonth = $day->month === $month->month;
                        $isToday = $key === $today;
                        $events = $inMonth ? ($byDay[$key] ?? collect()) : collect();
                    @endphp
                    <div role="gridcell" @if ($isToday) aria-current="date" @endif
                        @class([
                            'min-h-[7rem] border-b border-r border-gray-100 p-1.5',
                            'bg-gray-50/60' => ! $inMonth,
                            'bg-indigo-50/60' => $isToday,
                        ])>
                        <div class="flex justify-end">
                            <span @class([
                                'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                'bg-indigo-600 font-semibold text-white' => $isToday,
                                'text-gray-700' => $inMonth && ! $isToday,
                                'text-gray-400' => ! $inMonth,
                            ])>
                                <span class="sr-only">{{ $day->format('l j F') }}</span>
                                <span aria-hidden="true">{{ $day->day }}</span>
                            </span>
                        </div>
                        <ul class="mt-1 space-y-1">
                            @foreach ($events as $room)
                                @php($at = $room->scheduled_at ?? $room->started_at)
                                <li>
                                    <a href="{{ route('learn.rooms.show', $room) }}"
                                        class="block truncate rounded px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset hover:opacity-80 {{ $statusStyles[$room->status] ?? $statusStyles['scheduled'] }}"
                                        title="{{ $at->format('H:i') }} · {{ $room->title }} ({{ $room->statusLabel() }})">
                                        @if ($room->isLive())<span class="mr-0.5 inline-block h-1.5 w-1.5 rounded-full bg-red-600 align-middle" aria-hidden="true"></span>@endif
                                        <span class="tabular-nums">{{ $at->format('H:i') }}</span> {{ $room->title }}
                                        <span class="sr-only">— {{ $room->statusLabel() }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Agenda (mobile, and the "Add to calendar" list on every screen size) --}}
    @if ($rooms->isNotEmpty())
        <section class="mt-6" aria-labelledby="calendar-agenda">
            <h2 id="calendar-agenda" class="text-base font-semibold text-gray-900">Sessions in {{ $month->format('F') }}</h2>
            <div class="mt-3 space-y-4">
                @foreach ($byDay as $key => $events)
                    @php($date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $key))
                    <div>
                        <h3 @class([
                            'text-xs font-semibold uppercase tracking-wide',
                            'text-indigo-600' => $key === $today,
                            'text-gray-500' => $key !== $today,
                        ])>
                            {{ $date->format('l, d M') }} @if ($key === $today)· Today @endif
                        </h3>
                        <ul class="mbui-card mt-2 divide-y divide-gray-100">
                            @foreach ($events as $room)
                                @php($at = $room->scheduled_at ?? $room->started_at)
                                <li class="flex items-start gap-3 p-4">
                                    <span class="w-12 shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-gray-900">{{ $at->format('H:i') }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-learning.room-status :status="$room->status" />
                                            <a href="{{ route('learn.rooms.show', $room) }}" class="min-w-0 truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $room->title }}</a>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ (int) $room->duration_minutes }} min
                                            @if ($room->host) · {{ $room->host->name }} @endif
                                            @if ($room->category) · {{ $room->category->name }} @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        @if ($room->isLive())
                                            <x-mbui.btn-link :href="route('learn.rooms.live', $room)" variant="danger" class="px-3 py-1.5 text-xs">Join</x-mbui.btn-link>
                                        @elseif ($room->scheduled_at && $room->isScheduled())
                                            <a href="{{ route('learn.rooms.ics', $room) }}" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600"
                                                aria-label="Add {{ $room->title }} to your calendar" title="Add to calendar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m-2.25-10.5v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
