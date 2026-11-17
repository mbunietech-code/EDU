<x-layouts.user title="Research requests" header="Research requests">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Research requests</h1>
            <p class="mt-1 text-sm text-gray-500">Ask our team to do research work for you and follow every step until it is delivered.</p>
        </div>
        <x-mbui.btn-link :href="route('user.research-requests.create')">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            New request
        </x-mbui.btn-link>
    </div>

    {{-- Filters --}}
    @php($tabs = ['all' => 'All', 'active' => 'In progress', 'action' => 'Needs your action', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="-mx-1 flex gap-1 overflow-x-auto pb-1" aria-label="Filter requests">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('user.research-requests.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search ?: null])) }}"
                    @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition',
                        'bg-indigo-600 text-white shadow-sm' => $status === $key,
                        'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' => $status !== $key,
                    ])>
                    {{ $label }}
                    <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => $status === $key, 'bg-gray-100 text-gray-600' => $status !== $key, '!bg-amber-100 !text-amber-800' => $key === 'action' && $counts['action'] > 0 && $status !== $key])>{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" class="flex gap-2">
            @if ($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <label for="q" class="sr-only">Search</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="Search title or RR-number…" class="mbui-input sm:w-64">
        </form>
    </div>

    <div class="mt-5">
        @if ($requests->isEmpty())
            <x-mbui.card class="text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-gray-900">{{ $counts['all'] ? 'Nothing matches this filter' : 'No research requests yet' }}</h3>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">Need a proposal, a thesis chapter, data analysis or editing? Describe it and our researchers will take it from there.</p>
                <div class="mt-5"><x-mbui.btn-link :href="route('user.research-requests.create')">Ask for research work</x-mbui.btn-link></div>
            </x-mbui.card>
        @else
            <ul class="grid gap-4 md:grid-cols-2" role="list">
                @foreach ($requests as $rr)
                    @php($hex = $rr->isActive() ? ($rr->stage?->hex() ?? '#4F46E5') : ($rr->status === 'completed' ? '#059669' : '#9CA3AF'))
                    <li>
                        <a href="{{ route('user.research-requests.show', $rr) }}" class="mbui-card group block h-full p-5 transition hover:-translate-y-0.5 hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-500">{{ $rr->reference }} · {{ $rr->typeLabel() }}</p>
                                    <h3 class="mt-1 line-clamp-2 font-semibold text-gray-900 group-hover:text-indigo-700 [overflow-wrap:anywhere]">{{ $rr->title }}</h3>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium" style="background-color: {{ $hex }}14; color: {{ $hex }}; box-shadow: inset 0 0 0 1px {{ $hex }}33;">{{ $rr->stateLabel() }}</span>
                                    @if (in_array($rr->id, $unseen, true))
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600"><span class="h-2 w-2 rounded-full bg-indigo-600"></span>New update</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span>Progress</span><span class="font-semibold text-gray-900">{{ $rr->progress }}%</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full" style="width: {{ $rr->progress }}%; background-color: {{ $hex }}"></div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                                @if ($rr->deadline)
                                    <span @class(['font-semibold text-red-600' => $rr->isOverdue(), 'font-semibold text-amber-600' => $rr->isDueSoon()])>⏱ {{ $rr->deadlineLabel() }}</span>
                                @endif
                                <span>{{ $rr->levelLabel() }}</span>
                                @if ($rr->pages)<span>{{ $rr->pages }} pages</span>@endif
                                @if ($rr->hasPendingQuote())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">Price waiting: {{ $rr->quoteLabel() }}</span>
                                @endif
                                <span class="ml-auto">{{ $rr->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </div>

</x-layouts.user>
