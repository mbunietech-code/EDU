<x-layouts.admin title="Research requests" header="Research">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Research work requests</h1>
            <p class="mt-1 text-sm text-gray-500">Research work customers asked us to do — run each one through the process until it is delivered.</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <x-mbui.btn-link :href="route('admin.research-requests.stages.index')" variant="secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                Process stages
            </x-mbui.btn-link>
            <div class="inline-flex rounded-lg bg-white p-0.5 ring-1 ring-gray-300">
                @foreach (['list' => 'List', 'board' => 'Board'] as $key => $label)
                    <a href="{{ route('admin.research-requests.index', array_merge(request()->except('page', 'view'), ['view' => $key])) }}"
                        @class(['rounded-md px-3 py-1.5 text-sm font-semibold', 'bg-indigo-600 text-white' => $view === $key, 'text-gray-700 hover:bg-gray-50' => $view !== $key])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Summary --}}
    @php($cards = [
        ['Active', $summary['active'], ['status' => 'active'], 'text-indigo-600'],
        ['Need a look', $summary['attention'], ['status' => 'attention'], 'text-sky-600'],
        ['Unassigned', $summary['unassigned'], ['status' => 'active', 'assignee' => 'none'], 'text-gray-700'],
        ['Overdue', $summary['overdue'], ['status' => 'active', 'deadline' => 'overdue'], 'text-red-600'],
        ['Due in '.\App\Models\ResearchRequest::DUE_SOON_DAYS.' days', $summary['due_soon'], ['status' => 'active', 'deadline' => 'soon'], 'text-amber-600'],
        ['Quotes waiting', $summary['quotes'], null, 'text-amber-600'],
        ['Done this month', $summary['completed_month'], ['status' => 'completed'], 'text-emerald-600'],
        ['Avg. rating', $summary['rating'] ? $summary['rating'].' / 5' : '—', null, 'text-amber-500'],
    ])
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
        @foreach ($cards as [$label, $value, $params, $text])
            @if ($params)
                <a href="{{ route('admin.research-requests.index', ['view' => $view] + $params) }}" class="mbui-card block p-4 transition hover:shadow-md">
                    <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $text }}">{{ $value }}</p>
                </a>
            @else
                <div class="mbui-card p-4">
                    <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $text }}">{{ $value }}</p>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="mbui-card mt-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="sm:col-span-2">
            <label for="q" class="sr-only">Search</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" placeholder="Search title, RR-number, customer…" class="mbui-input">
        </div>
        <select name="status" class="mbui-input" aria-label="Status" onchange="this.form.submit()">
            @foreach (['active' => 'Active', 'attention' => 'Needs a look', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All statuses'] as $k => $l)
                <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="priority" class="mbui-input" aria-label="Priority" onchange="this.form.submit()">
            <option value="">Any priority</option>
            @foreach (\App\Models\ResearchRequest::PRIORITIES as $k => $l)
                <option value="{{ $k }}" @selected($filters['priority'] === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="assignee" class="mbui-input" aria-label="Assigned to" onchange="this.form.submit()">
            <option value="">Anyone</option>
            <option value="me" @selected($filters['assignee'] === 'me')>Mine</option>
            <option value="none" @selected($filters['assignee'] === 'none')>Unassigned</option>
            @foreach ($people as $p)
                <option value="{{ $p->id }}" @selected($filters['assignee'] === (string) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="deadline" class="mbui-input" aria-label="Deadline" onchange="this.form.submit()">
            <option value="">Any deadline</option>
            <option value="overdue" @selected($filters['deadline'] === 'overdue')>Overdue</option>
            <option value="soon" @selected($filters['deadline'] === 'soon')>Due in {{ \App\Models\ResearchRequest::DUE_SOON_DAYS }} days</option>
            <option value="week" @selected($filters['deadline'] === 'week')>Due this week</option>
        </select>
        @if ($view === 'list')
            <select name="stage" class="mbui-input sm:col-span-2 lg:col-span-1" aria-label="Stage" onchange="this.form.submit()">
                <option value="">Any stage</option>
                @foreach ($stages as $s)
                    <option value="{{ $s->id }}" @selected((int) $filters['stage'] === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        @endif
        <div class="flex gap-2 {{ $view === 'list' ? 'lg:col-span-5' : 'lg:col-span-6' }} lg:justify-end">
            <x-mbui.button type="submit">Filter</x-mbui.button>
            <x-mbui.btn-link :href="route('admin.research-requests.index', ['view' => $view])" variant="ghost">Reset</x-mbui.btn-link>
        </div>
    </form>

    @if ($view === 'list')
        <div class="mt-6">
            @if ($requests->isEmpty())
                <x-mbui.card><x-mbui.empty-state title="No research requests here" message="Try another filter — new requests from customers land here." /></x-mbui.card>
            @else
                <x-mbui.table>
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="mbui-th">Request</th>
                            <th class="mbui-th">Customer</th>
                            <th class="mbui-th">Stage</th>
                            <th class="mbui-th">Deadline</th>
                            <th class="mbui-th">Priority</th>
                            <th class="mbui-th">Assigned</th>
                            <th class="mbui-th">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($requests as $rr)
                            <tr @class(['bg-sky-50/40' => in_array($rr->id, $unseenIds, true)])>
                                <td class="mbui-td max-w-xs">
                                    <a href="{{ route('admin.research-requests.show', $rr) }}" class="font-semibold text-gray-900 hover:text-indigo-600 [overflow-wrap:anywhere]">
                                        @if (in_array($rr->id, $unseenIds, true))<span class="mr-1 inline-block h-2 w-2 rounded-full bg-sky-500" title="New activity"></span>@endif{{ $rr->title }}
                                    </a>
                                    <p class="text-xs text-gray-500">{{ $rr->reference }} · {{ $rr->typeLabel() }} · {{ $rr->levelLabel() }}</p>
                                </td>
                                <td class="mbui-td"><span class="text-sm text-gray-900">{{ $rr->user?->name }}</span><p class="text-xs text-gray-500">{{ $rr->created_at->diffForHumans() }}</p></td>
                                <td class="mbui-td min-w-[10rem]">
                                    @if ($rr->isActive() && $rr->stage)
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium" style="{{ $rr->stage->badgeStyle() }}">{{ $rr->stage->name }}</span>
                                    @else
                                        <x-mbui.badge :appearance="$rr->status === 'completed' ? 'success' : 'neutral'">{{ $rr->statusLabel() }}</x-mbui.badge>
                                    @endif
                                    <div class="mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-indigo-500" style="width: {{ $rr->progress }}%"></div></div>
                                </td>
                                <td class="mbui-td whitespace-nowrap">
                                    @if ($rr->deadline)
                                        <span @class(['text-sm', 'font-semibold text-red-600' => $rr->isOverdue(), 'font-semibold text-amber-600' => $rr->isDueSoon(), 'text-gray-700' => ! $rr->isOverdue() && ! $rr->isDueSoon()])>{{ $rr->deadline->format('d M') }}</span>
                                        <p class="text-xs text-gray-500">{{ $rr->deadlineLabel() }}</p>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="mbui-td"><x-mbui.badge :appearance="$rr->priorityAppearance()">{{ $rr->priorityLabel() }}</x-mbui.badge></td>
                                <td class="mbui-td text-sm">{{ $rr->assignee?->name ?? '—' }}</td>
                                <td class="mbui-td whitespace-nowrap text-sm">
                                    {{ $rr->quoteLabel() ?? ($rr->budgetLabel() ? 'Budget '.$rr->budgetLabel() : '—') }}
                                    @if ($rr->quote_status)<p class="text-xs {{ ['pending' => 'text-amber-600', 'accepted' => 'text-emerald-600', 'declined' => 'text-red-600'][$rr->quote_status] ?? 'text-gray-500' }}">{{ ucfirst($rr->quote_status) }}</p>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-mbui.table>
                <div class="mt-6">{{ $requests->links() }}</div>
            @endif
        </div>
    @else
        {{-- Kanban: drag a card to another stage (research_requests.manage) --}}
        <div class="mt-6 -mx-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:px-0"
            x-data="{
                dragging: null,
                busy: false,
                canManage: @js($canManage),
                async drop(stageId, el) {
                    if (! this.canManage || ! this.dragging || this.dragging.dataset.stage == stageId) return;
                    const card = this.dragging;
                    const from = card.parentElement;
                    el.querySelector('[data-list]').prepend(card);
                    card.dataset.stage = stageId;
                    this.busy = true;
                    try {
                        const res = await fetch(card.dataset.url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ stage_id: stageId, notify: true }),
                        });
                        if (! res.ok) throw new Error();
                        const data = await res.json();
                        card.querySelector('[data-progress]').style.width = data.progress + '%';
                        card.querySelector('[data-progress-label]').textContent = data.progress + '%';
                    } catch (e) {
                        from.prepend(card);
                        alert('Could not move this request. Please try again.');
                    }
                    this.busy = false;
                    this.recount();
                },
                recount() { this.$root.querySelectorAll('[data-column]').forEach(c => c.querySelector('[data-count]').textContent = c.querySelectorAll('[data-card]').length) },
            }">
            <div class="flex min-w-max gap-4">
                @foreach ($columns as $col)
                    @php($s = $col['stage'])
                    <section data-column class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-100/80 p-2"
                        @dragover.prevent="$el.classList.add('ring-2','ring-indigo-400')" @dragleave="$el.classList.remove('ring-2','ring-indigo-400')"
                        @drop.prevent="$el.classList.remove('ring-2','ring-indigo-400'); drop({{ $s->id }}, $el)">
                        <header class="flex items-center justify-between px-2 py-1.5">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $s->hex() }}"></span>
                                <h2 class="text-sm font-semibold text-gray-800">{{ $s->name }}</h2>
                                <span data-count class="rounded-full bg-white px-1.5 text-xs font-semibold text-gray-600">{{ $col['items']->count() }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ $s->progress }}%</span>
                        </header>
                        <div data-list class="mt-1 min-h-[6rem] flex-1 space-y-2">
                            @foreach ($col['items'] as $rr)
                                <article data-card data-stage="{{ $s->id }}" data-url="{{ route('admin.research-requests.stage', $rr) }}"
                                    @if ($canManage) draggable="true" @dragstart="dragging = $el; $el.classList.add('opacity-50')" @dragend="$el.classList.remove('opacity-50'); dragging = null" @endif
                                    @class(['rounded-lg border bg-white p-3 shadow-sm transition hover:shadow-md', 'cursor-grab' => $canManage, 'border-red-300' => $rr->isOverdue(), 'border-gray-200' => ! $rr->isOverdue()])>
                                    <div class="flex items-start justify-between gap-2">
                                        <a href="{{ route('admin.research-requests.show', $rr) }}" class="text-sm font-semibold leading-snug text-gray-900 hover:text-indigo-600 [overflow-wrap:anywhere]">
                                            @if (in_array($rr->id, $unseenIds, true))<span class="mr-1 inline-block h-2 w-2 rounded-full bg-sky-500" title="New activity"></span>@endif{{ \Illuminate\Support\Str::limit($rr->title, 70) }}
                                        </a>
                                        @if (in_array($rr->priority, ['urgent', 'high'], true))
                                            <x-mbui.badge :appearance="$rr->priorityAppearance()" class="shrink-0 !px-1.5 !py-0.5">{{ $rr->priorityLabel() }}</x-mbui.badge>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ $rr->reference }} · {{ $rr->user?->name }}</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100"><div data-progress class="h-full rounded-full" style="width: {{ $rr->progress }}%; background-color: {{ $s->hex() }}"></div></div>
                                        <span data-progress-label class="text-[11px] font-semibold text-gray-600">{{ $rr->progress }}%</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                                        @if ($rr->deadline)
                                            <span @class(['inline-flex items-center gap-1', 'font-semibold text-red-600' => $rr->isOverdue(), 'font-semibold text-amber-600' => $rr->isDueSoon()])><x-research-requests.icon name="clock" class="h-3.5 w-3.5" /> {{ $rr->deadlineLabel() }}</span>
                                        @endif
                                        @if ($rr->quote_status === 'pending')<span class="font-semibold text-amber-600">Quote waiting</span>@endif
                                        @if ($rr->revision_count)<span class="inline-flex items-center gap-0.5" title="Revisions"><x-research-requests.icon name="revision" class="h-3 w-3" /> {{ $rr->revision_count }}</span>@endif
                                        <span class="ml-auto inline-flex items-center gap-1">
                                            @if ($rr->assignee)
                                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700" title="{{ $rr->assignee->name }}">{{ mb_strtoupper(mb_substr($rr->assignee->name, 0, 1)) }}</span>
                                            @else
                                                <span class="text-gray-400">Unassigned</span>
                                            @endif
                                        </span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            @if ($canManage)
                <p class="mt-2 text-xs text-gray-500">Drag a card to another column to move it — the customer is told. Open a card to add a note or set a custom %.</p>
            @endif
        </div>
    @endif

</x-layouts.admin>
