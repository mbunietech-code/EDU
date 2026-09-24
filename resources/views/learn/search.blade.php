<x-layouts.app title="Search Learning" header="Learning">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Search</h1>
            <p class="mt-1 text-sm text-gray-500">Find lessons, courses, topics, live classes and instructors.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('learn.search') }}" role="search" class="mbui-card mt-6 p-4" x-data="{ more: @js($hasFilters) }">
        <input type="hidden" name="type" value="{{ $filters['type'] }}">
        <div class="flex flex-col gap-2 sm:flex-row">
            <label for="search-q" class="sr-only">Search term</label>
            <div class="relative min-w-0 flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input id="search-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" autofocus
                    placeholder="What do you want to learn?" class="mbui-input w-full pl-9">
            </div>
            <div class="flex gap-2">
                <x-mbui.button type="submit" class="flex-1 sm:flex-none">Search</x-mbui.button>
                <x-mbui.button variant="secondary" @click="more = ! more" x-bind:aria-expanded="more.toString()" aria-controls="search-filters">
                    Filters
                </x-mbui.button>
            </div>
        </div>

        <div id="search-filters" x-show="more" x-cloak class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div>
                <label for="search-category" class="mbui-label">Category</label>
                <select id="search-category" name="category" class="mbui-input mt-1 w-full">
                    <option value="">Any category</option>
                    @foreach ($categoryOptions as $cat)
                        <option value="{{ $cat->slug }}" @selected($filters['category'] === $cat->slug)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search-course" class="mbui-label">Course</label>
                <select id="search-course" name="course" class="mbui-input mt-1 w-full">
                    <option value="">Any course</option>
                    @foreach ($courseOptions as $c)
                        <option value="{{ $c->slug }}" @selected($filters['course'] === $c->slug)>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search-duration" class="mbui-label">Duration</label>
                <select id="search-duration" name="duration" class="mbui-input mt-1 w-full">
                    <option value="">Any length</option>
                    @foreach ($durations as $key => $label)
                        <option value="{{ $key }}" @selected($filters['duration'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search-mode" class="mbui-label">Format</label>
                <select id="search-mode" name="mode" class="mbui-input mt-1 w-full">
                    <option value="">Live &amp; recorded</option>
                    @foreach ($modes as $key => $label)
                        <option value="{{ $key }}" @selected($filters['mode'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search-status" class="mbui-label">My progress</label>
                <select id="search-status" name="status" class="mbui-input mt-1 w-full">
                    <option value="">Any</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search-sort" class="mbui-label">Sort by</label>
                <select id="search-sort" name="sort" class="mbui-input mt-1 w-full">
                    @foreach ($sorts as $key => $label)
                        <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3 sm:col-span-2 lg:col-span-3 xl:col-span-6">
                <x-mbui.button type="submit">Apply filters</x-mbui.button>
                @if ($hasFilters || $filters['sort'] !== 'latest')
                    <a href="{{ route('learn.search', array_filter(['q' => $filters['q'], 'type' => $filters['type'] !== 'all' ? $filters['type'] : null])) }}" class="mbui-anchor text-sm">Clear filters</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Type tabs --}}
    <div class="mt-6 overflow-x-auto border-b border-gray-200">
        <nav class="-mb-px flex gap-6" aria-label="Result type">
            @foreach ($types as $key => $label)
                @php($active = $filters['type'] === $key)
                <a href="{{ $seeAll($key) }}" @if ($active) aria-current="page" @endif
                    @class([
                        'shrink-0 whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                        'border-indigo-600 text-indigo-600' => $active,
                        'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! $active,
                    ])>
                    {{ $label }}
                    @if ($filters['type'] === 'all' && isset($groups[$key]))
                        <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $groups[$key]['total'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6">
        @if ($idle)
            <x-learning.empty title="Start typing to search."
                message="Search every lesson, course, topic, live class and instructor available to you."
                :action-href="route('learn.courses.index')" action-label="Browse courses instead" />
        @elseif ($totalFound === 0)
            <x-learning.empty
                :title="$filters['q'] !== '' ? 'No results for “'.$filters['q'].'”.' : 'No results match these filters.'"
                message="Check the spelling, try a broader term or remove some filters."
                :action-href="route('learn.search', array_filter(['q' => $filters['q']]))" :action-label="$hasFilters || $filters['type'] !== 'all' ? 'Search everything' : null" />
        @elseif ($filters['type'] === 'all')
            <p class="mb-4 text-sm text-gray-500">
                {{ number_format($totalFound) }} {{ Str::plural('result', $totalFound) }}
                @if ($filters['q'] !== '') for <span class="font-medium text-gray-700">“{{ $filters['q'] }}”</span>@endif
            </p>
            <div class="space-y-8">
                @foreach ($groups as $type => $group)
                    @continue($group['total'] === 0)
                    <section aria-labelledby="results-{{ $type }}">
                        @include('learn.partials.section-header', [
                            'id' => 'results-'.$type, 'title' => $group['label'], 'count' => $group['total'],
                            'href' => $group['total'] > $group['items']->count() ? $seeAll($type) : null,
                            'linkLabel' => 'See all '.$group['total'],
                        ])
                        <div class="mt-3">
                            @include('learn.partials.search-results', ['type' => $type, 'items' => $group['items']])
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <p class="mb-4 text-sm text-gray-500">
                {{ number_format($results->total()) }} {{ Str::lower($types[$filters['type']]) }}
                @if ($filters['q'] !== '') for <span class="font-medium text-gray-700">“{{ $filters['q'] }}”</span>@endif
            </p>
            @include('learn.partials.search-results', ['type' => $filters['type'], 'items' => $results])
            @if ($results->hasPages())
                <div class="mt-6">{{ $results->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts.app>
