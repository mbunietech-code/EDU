<x-layouts.app title="Live classes" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Live classes</h1>
            <p class="mt-1 text-sm text-gray-500">Join live sessions with your instructors, ask questions and catch up on recordings.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('learn.calendar')" variant="secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                Calendar
            </x-mbui.btn-link>
        </div>
    </div>

    {{-- Tabs --}}
    @php($tabBase = request()->except(['tab', 'page']))
    <nav class="mt-6 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Live class tabs">
        <div class="flex min-w-max gap-1 border-b border-gray-200 text-sm">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('learn.rooms.index', array_merge($tabBase, ['tab' => $key])) }}"
                    @if ($tab === $key) aria-current="page" @endif
                    @class([
                        'inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 font-medium',
                        'border-indigo-600 text-indigo-600' => $tab === $key,
                        'border-transparent text-gray-500 hover:text-gray-700' => $tab !== $key,
                    ])>
                    @if ($key === 'live' && $counts['live'] > 0)
                        <span class="relative flex h-2 w-2" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span>
                        </span>
                    @endif
                    {{ $label }}
                    <span @class([
                        'rounded-full px-1.5 text-xs',
                        'bg-red-600 text-white' => $key === 'live' && $counts['live'] > 0,
                        'bg-gray-100 text-gray-600' => ! ($key === 'live' && $counts['live'] > 0),
                    ])>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    @include('learn.rooms.partials.filters')

    <div class="mt-6">
        @if ($rooms->isEmpty())
            @if ($isFiltered)
                <x-learning.empty title="No live classes match your filters"
                    message="Try a different search term, category or course."
                    :action-href="route('learn.rooms.index', ['tab' => $tab])" action-label="Clear filters" />
            @elseif ($tab === 'live')
                <x-learning.empty title="No live sessions available."
                    message="Nothing is on air right now. Check the upcoming classes to see what is next."
                    :action-href="route('learn.rooms.index', ['tab' => 'upcoming'])" action-label="See upcoming classes" />
            @elseif ($tab === 'upcoming')
                <x-learning.empty title="No upcoming classes."
                    message="When your instructors schedule a live class it will show up here." />
            @elseif ($tab === 'completed')
                <x-learning.empty title="No completed classes yet."
                    message="Classes that have finished — and their shared recordings — will appear here." />
            @else
                <x-learning.empty title="No live classes yet."
                    message="Live classes you can join will appear here." />
            @endif
        @else
            <p class="mb-3 text-sm text-gray-500">
                {{ $rooms->total() }} {{ Str::plural('class', $rooms->total()) }}
                @if ($activeCategory) in <span class="font-medium text-gray-700">{{ $activeCategory->name }}</span>@endif
                @if ($activeCourse) · <span class="font-medium text-gray-700">{{ $activeCourse->title }}</span>@endif
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rooms as $room)
                    <x-learning.room-card :room="$room" />
                @endforeach
            </div>
            <div class="mt-6">{{ $rooms->links() }}</div>
        @endif
    </div>
</x-layouts.app>
