<x-layouts.admin :title="$rr->reference.' · '.$rr->title" header="Research">

    <div class="mbui-page-header">
        <div class="min-w-0">
            <a href="{{ route('admin.research-requests.index') }}" class="mbui-anchor text-sm">&larr; Research requests</a>
            <p class="mt-2 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $rr->reference }} · {{ $rr->typeLabel() }} · {{ $rr->levelLabel() }}</p>
            <h1 class="mbui-title mt-1 [overflow-wrap:anywhere]">{{ $rr->title }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <x-mbui.badge :appearance="$rr->priorityAppearance()">{{ $rr->priorityLabel() }} priority</x-mbui.badge>
                <x-mbui.badge :appearance="$rr->status === 'completed' ? 'success' : ($rr->status === 'cancelled' ? 'neutral' : 'info')">{{ $rr->statusLabel() }}</x-mbui.badge>
                @if ($rr->deadline)
                    <span @class(['inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold', 'bg-red-100 text-red-700' => $rr->isOverdue(), 'bg-amber-100 text-amber-800' => $rr->isDueSoon(), 'bg-gray-100 text-gray-700' => ! $rr->isOverdue() && ! $rr->isDueSoon()])>
                        <x-research-requests.icon name="clock" class="h-3.5 w-3.5" /> {{ $rr->deadline->format('D, d M Y') }} @if ($rr->isActive()) · {{ $rr->deadlineLabel() }} @endif
                    </span>
                @endif
                @if ($rr->revision_count)<span class="inline-flex items-center gap-1 text-xs text-gray-500"><x-research-requests.icon name="revision" class="h-3.5 w-3.5" /> {{ $rr->revision_count }} revision{{ $rr->revision_count > 1 ? 's' : '' }}</span>@endif
                @if ($rr->rating)<x-research-requests.stars :rating="$rr->rating" />@endif
            </div>
        </div>
        @if ($canManage)
            <div class="flex shrink-0 flex-wrap gap-2" x-data="{ modal: null }">
                @if ($rr->isActive())
                    <x-mbui.button variant="secondary" @click="modal = 'cancel'">Cancel request</x-mbui.button>
                @else
                    <x-mbui.button variant="secondary" @click="modal = 'reopen'">Reopen</x-mbui.button>
                @endif
                <x-mbui.button variant="secondary" class="text-red-600" @click="modal = 'delete'">Delete</x-mbui.button>

                <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @keydown.escape.window="modal = null">
                    <div class="mbui-card w-full max-w-md p-6" @click.outside="modal = null">
                        <form x-show="modal === 'cancel'" method="POST" action="{{ route('admin.research-requests.cancel', $rr) }}">
                            @csrf
                            <h2 class="text-base font-semibold text-gray-900">Cancel {{ $rr->reference }}?</h2>
                            <p class="mt-1 text-sm text-gray-500">The customer is told, with your reason.</p>
                            <textarea name="reason" rows="3" required maxlength="500" class="mbui-input mt-3" placeholder="Reason the customer will read"></textarea>
                            <div class="mt-4 flex justify-end gap-2"><x-mbui.button variant="secondary" @click="modal = null">Back</x-mbui.button><x-mbui.button type="submit" variant="danger">Cancel request</x-mbui.button></div>
                        </form>
                        <form x-show="modal === 'reopen'" method="POST" action="{{ route('admin.research-requests.reopen', $rr) }}">
                            @csrf
                            <h2 class="text-base font-semibold text-gray-900">Reopen {{ $rr->reference }}?</h2>
                            <textarea name="note" rows="3" maxlength="1000" class="mbui-input mt-3" placeholder="Note for the customer (optional)"></textarea>
                            <div class="mt-4 flex justify-end gap-2"><x-mbui.button variant="secondary" @click="modal = null">Back</x-mbui.button><x-mbui.button type="submit">Reopen</x-mbui.button></div>
                        </form>
                        <form x-show="modal === 'delete'" method="POST" action="{{ route('admin.research-requests.destroy', $rr) }}">
                            @csrf
                            @method('DELETE')
                            <h2 class="text-base font-semibold text-gray-900">Delete {{ $rr->reference }} for good?</h2>
                            <p class="mt-1 text-sm text-gray-500">Messages and files are removed. A snapshot stays in Deleted items. Use this for spam only — otherwise cancel.</p>
                            <input name="reason" required maxlength="500" class="mbui-input mt-3" placeholder="Why delete?">
                            <div class="mt-4 flex justify-end gap-2"><x-mbui.button variant="secondary" @click="modal = null">Back</x-mbui.button><x-mbui.button type="submit" variant="danger">Delete</x-mbui.button></div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($rr->status === 'cancelled' && $rr->cancel_reason)
        <x-mbui.alert type="warning" class="mt-6">Cancelled {{ $rr->cancelled_at?->format('d M Y') }}: {{ $rr->cancel_reason }}</x-mbui.alert>
    @endif
    @if ($rr->review)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-gray-800"><span class="font-semibold">Customer review:</span> “{{ $rr->review }}”</div>
    @endif

    <x-research-requests.tracker :tracker="$tracker" :rr="$rr" class="mt-6" />

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Brief --}}
            <section class="mbui-card p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900">The brief</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs text-gray-500">Field</dt><dd class="font-medium text-gray-900">{{ $rr->field_of_study ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Length</dt><dd class="font-medium text-gray-900">{{ $rr->pages ? $rr->pages.' pages (~'.number_format($rr->pages * 275).' words)' : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Citation</dt><dd class="font-medium text-gray-900">{{ $rr->citationLabel() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Budget</dt><dd class="font-medium text-gray-900">{{ $rr->budgetLabel() ?? '—' }}</dd></div>
                    @if ($rr->purpose)<div class="col-span-2 sm:col-span-4"><dt class="text-xs text-gray-500">Purpose</dt><dd class="text-gray-900">{{ $rr->purpose }}</dd></div>@endif
                </dl>
                <div class="mt-4 rounded-lg bg-gray-50 p-4">
                    <p class="whitespace-pre-line text-sm text-gray-800 [overflow-wrap:anywhere]">{{ $rr->description }}</p>
                </div>
                @php($brief = $rr->files->where('kind', 'brief'))
                @if ($brief->isNotEmpty())
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($brief as $file)
                            <x-research-requests.file-link :file="$file" :href="route('admin.research-requests.file', [$rr, $file])" />
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Messages & internal notes --}}
            <section id="messages" class="mbui-card p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Messages with {{ $rr->user?->name ?? 'the customer' }}</h2>
                    <span class="text-xs text-gray-500"><span class="inline-block h-2 w-2 rounded-sm bg-amber-300"></span> internal notes are staff only</span>
                </div>
                <div class="mt-4 max-h-[36rem] space-y-3 overflow-y-auto pr-1" x-data x-init="$el.scrollTop = $el.scrollHeight">
                    @forelse ($rr->messages as $msg)
                        @php($staff = $msg->is_from_admin)
                        <div @class(['flex', 'justify-end' => $staff])>
                            <div @class([
                                'max-w-[85%] rounded-2xl px-4 py-2.5',
                                'rounded-br-sm border border-dashed border-amber-300 bg-amber-50 text-gray-900' => $msg->is_internal,
                                'rounded-br-sm bg-indigo-600 text-white' => $staff && ! $msg->is_internal,
                                'rounded-bl-sm bg-gray-100 text-gray-900' => ! $staff,
                            ])>
                                <p @class(['text-xs', 'text-indigo-100' => $staff && ! $msg->is_internal, 'text-gray-500' => ! $staff || $msg->is_internal])>
                                    @if ($msg->is_internal)<span class="inline-flex items-center gap-1 font-semibold text-amber-700"><x-research-requests.icon name="lock" class="h-3 w-3" /> Internal ·</span>@endif
                                    {{ $msg->user?->name ?? 'Former user' }}{{ $staff ? ' · Team' : ' · Customer' }} · {{ $msg->created_at->format('d M, H:i') }}
                                </p>
                                @if ($msg->body)<p class="mt-0.5 whitespace-pre-line text-sm [overflow-wrap:anywhere]">{{ $msg->body }}</p>@endif
                                @foreach ($msg->files as $file)
                                    <a href="{{ route('admin.research-requests.file', [$rr, $file]) }}" @class(['mt-1.5 flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium', 'bg-white/15 hover:bg-white/25' => $staff && ! $msg->is_internal, 'bg-white hover:bg-gray-50' => ! $staff || $msg->is_internal])>
                                        <x-research-requests.icon name="paperclip" class="h-3.5 w-3.5" /> <span class="truncate">{{ $file->original_name }}</span><span class="shrink-0 opacity-70">{{ $file->sizeLabel() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-center text-sm text-gray-500">No messages yet.</p>
                    @endforelse
                </div>

                @if ($canManage)
                    <form method="POST" action="{{ route('admin.research-requests.message', $rr) }}" enctype="multipart/form-data" class="mt-4 border-t border-gray-100 pt-4" x-data="{ internal: false, n: 0 }">
                        @csrf
                        <input type="hidden" name="internal" :value="internal ? 1 : 0">
                        <div class="mb-2 inline-flex rounded-lg bg-gray-100 p-0.5 text-sm">
                            <button type="button" @click="internal = false" class="rounded-md px-3 py-1 font-medium" :class="! internal ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'">Reply to customer</button>
                            <button type="button" @click="internal = true" class="rounded-md px-3 py-1 font-medium" :class="internal ? 'bg-amber-100 text-amber-900 shadow-sm' : 'text-gray-600'"><span class="inline-flex items-center gap-1"><x-research-requests.icon name="lock" class="h-3.5 w-3.5" /> Internal note</span></button>
                        </div>
                        <label for="body" class="sr-only">Message</label>
                        <textarea id="body" name="body" rows="3" maxlength="5000" class="mbui-input" :class="internal && '!border-amber-300 !bg-amber-50'"
                            :placeholder="internal ? 'Only the team sees this…' : 'Write to the customer…'"></textarea>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-600 hover:text-indigo-600">
                                <x-research-requests.icon name="paperclip" class="h-5 w-5" /> <span x-text="n ? n + ' file(s)' : 'Attach files'"></span>
                                <input type="file" name="files[]" multiple class="sr-only" @change="n = $event.target.files.length">
                            </label>
                            <x-mbui.button type="submit"><span x-text="internal ? 'Save note' : 'Send'">Send</span></x-mbui.button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- Activity history --}}
            <section class="mbui-card p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900">Activity history</h2>
                <ol class="relative mt-4 space-y-4 border-l border-gray-200 pl-5" role="list">
                    @foreach ($rr->events as $event)
                        <li class="relative">
                            <span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full ring-4 ring-white" style="background-color: {{ $event->toStage?->hex() ?? ($event->is_internal ? '#F59E0B' : '#9CA3AF') }}"></span>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $event->label() }}
                                @if ($event->progress !== null && in_array($event->type, ['stage', 'progress'], true))<span class="font-normal text-gray-500">· {{ $event->progress }}%</span>@endif
                                @if ($event->is_internal)<span class="ml-1 rounded bg-amber-100 px-1.5 text-[10px] font-semibold uppercase text-amber-800">staff only</span>@endif
                            </p>
                            @if ($event->note)<p class="mt-0.5 whitespace-pre-line text-sm text-gray-600 [overflow-wrap:anywhere]"><span class="text-xs text-gray-400">To customer:</span> {{ $event->note }}</p>@endif
                            @if ($event->internal_note)<p class="mt-0.5 whitespace-pre-line rounded bg-amber-50 px-2 py-1 text-sm text-amber-900 [overflow-wrap:anywhere]"><span class="mr-1 font-semibold">Internal:</span>{{ $event->internal_note }}</p>@endif
                            <p class="text-xs text-gray-400">{{ $event->user?->name ?? 'System' }} · {{ $event->created_at->format('d M Y, H:i') }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            @if ($canManage)
                {{-- Move stage --}}
                <section class="mbui-card p-5" x-data="{
                    stages: @js($stages->mapWithKeys(fn ($s) => [$s->id => $s->progress])),
                    stage: @js($rr->stage_id),
                    progress: @js($rr->progress),
                    pick() { this.progress = Math.max(this.stages[this.stage] ?? 0, this.stage == @js($rr->stage_id) ? @js($rr->progress) : 0) },
                }">
                    <h2 class="text-sm font-semibold text-gray-900">Move the process</h2>
                    <form method="POST" action="{{ route('admin.research-requests.stage', $rr) }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label for="stage_id" class="mbui-label">Stage</label>
                            <select id="stage_id" name="stage_id" x-model="stage" @change="pick()" class="mbui-input mt-1">
                                @foreach ($stages as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->progress }}%)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between"><label for="progress" class="mbui-label">Progress</label><span class="text-sm font-bold text-indigo-600" x-text="progress + '%'"></span></div>
                            <input id="progress" type="range" name="progress" min="0" max="100" step="5" x-model="progress" class="mt-1 w-full accent-indigo-600">
                        </div>
                        <div>
                            <label for="note" class="mbui-label">Note to the customer <span class="font-normal text-gray-400">(optional)</span></label>
                            <textarea id="note" name="note" rows="2" maxlength="3000" class="mbui-input mt-1" placeholder="e.g. Chapter 2 is half way, data collection starts Monday"></textarea>
                        </div>
                        <div>
                            <label for="internal_note" class="mbui-label">Internal note <span class="font-normal text-gray-400">(staff only)</span></label>
                            <textarea id="internal_note" name="internal_note" rows="2" maxlength="3000" class="mbui-input mt-1 !bg-amber-50/60"></textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked class="rounded border-gray-300 text-indigo-600"> Tell the customer (app + website)</label>
                        <x-mbui.button type="submit" class="w-full">Update stage</x-mbui.button>
                    </form>
                </section>

                {{-- Quote --}}
                <section class="mbui-card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">Price quote</h2>
                        @if ($rr->quote_status)
                            <x-mbui.badge :appearance="['pending' => 'warning', 'accepted' => 'success', 'declined' => 'danger'][$rr->quote_status] ?? 'neutral'">{{ ucfirst($rr->quote_status) }}</x-mbui.badge>
                        @endif
                    </div>
                    @if ($rr->quote_amount !== null)
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $rr->quoteLabel() }}</p>
                        <p class="text-xs text-gray-500">Sent {{ $rr->quoted_at?->diffForHumans() }}@if ($rr->quote_answered_at) · answered {{ $rr->quote_answered_at->diffForHumans() }}@endif</p>
                    @elseif ($rr->budgetLabel())
                        <p class="mt-2 text-xs text-gray-500">Customer budget: <span class="font-semibold text-gray-800">{{ $rr->budgetLabel() }}</span></p>
                    @endif
                    @if ($rr->isActive())
                        <form method="POST" action="{{ route('admin.research-requests.quote', $rr) }}" class="mt-3 space-y-2">
                            @csrf
                            <div class="flex">
                                <select name="quote_currency" aria-label="Currency" class="mbui-input !w-24 rounded-r-none">
                                    @foreach (\App\Models\ResearchRequest::CURRENCIES as $c)
                                        <option value="{{ $c }}" @selected(($rr->quote_currency ?: $rr->currency) === $c)>{{ $c }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="quote_amount" min="0" step="any" required value="{{ $rr->quote_amount !== null ? (float) $rr->quote_amount : '' }}" class="mbui-input rounded-l-none border-l-0" placeholder="Amount" aria-label="Amount">
                            </div>
                            <textarea name="quote_note" rows="2" maxlength="3000" class="mbui-input" placeholder="What the price covers, how to pay, timeline…">{{ $rr->quote_note }}</textarea>
                            <x-mbui.button type="submit" variant="secondary" class="w-full">{{ $rr->quote_amount !== null ? 'Send new price' : 'Send price' }}</x-mbui.button>
                        </form>
                    @endif
                </section>

                {{-- Deliver --}}
                <section class="mbui-card p-5" x-data="{ kind: 'draft', n: 0 }">
                    <h2 class="text-sm font-semibold text-gray-900">Deliver work</h2>
                    <form method="POST" action="{{ route('admin.research-requests.deliver', $rr) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="kind" :value="kind">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="kind = 'draft'" class="rounded-lg border px-3 py-2 text-sm font-medium" :class="kind === 'draft' ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-gray-200 text-gray-700'">Draft</button>
                            <button type="button" @click="kind = 'final'" class="rounded-lg border px-3 py-2 text-sm font-medium" :class="kind === 'final' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-700'">Final work</button>
                        </div>
                        <label class="flex cursor-pointer flex-col items-center rounded-lg border-2 border-dashed border-gray-300 px-3 py-4 text-center text-sm hover:border-indigo-400">
                            <span class="font-medium text-indigo-600" x-text="n ? n + ' file(s) chosen' : 'Choose files'"></span>
                            <span class="text-xs text-gray-500">Up to {{ \App\Models\ResearchRequestFile::MAX_KB / 1024 }} MB each</span>
                            <input type="file" name="files[]" multiple required class="sr-only" @change="n = $event.target.files.length">
                        </label>
                        <input name="note" maxlength="500" class="mbui-input" placeholder="Short note (e.g. Chapters 1–3)">
                        <label class="flex items-center gap-2 text-xs text-gray-600"><input type="hidden" name="move_stage" value="0"><input type="checkbox" name="move_stage" value="1" checked class="rounded border-gray-300 text-indigo-600"> <span x-text="kind === 'final' ? 'Move to “Delivered”' : 'Move to “Draft ready”'"></span></label>
                        <x-mbui.button type="submit" class="w-full" ::class="kind === 'final' ? '!bg-emerald-600 hover:!bg-emerald-500' : ''">Upload &amp; tell the customer</x-mbui.button>
                    </form>
                </section>
            @endif

            {{-- Delivered files --}}
            @php($delivered = $rr->files->whereIn('kind', ['draft', 'final']))
            <section class="mbui-card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Delivered files</h2>
                @forelse ($delivered as $file)
                    <div class="mt-2">
                        <p class="mb-1 text-[11px] font-semibold uppercase {{ $file->kind === 'final' ? 'text-emerald-700' : 'text-sky-700' }}">{{ $file->kindLabel() }} · {{ $file->user?->name }}</p>
                        <x-research-requests.file-link :file="$file" :href="route('admin.research-requests.file', [$rr, $file])" />
                    </div>
                @empty
                    <p class="mt-2 text-sm text-gray-400">Nothing delivered yet.</p>
                @endforelse
            </section>

            {{-- Assign + details --}}
            <section class="mbui-card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Team &amp; details</h2>
                @if ($canManage)
                    <form method="POST" action="{{ route('admin.research-requests.assign', $rr) }}" class="mt-3">
                        @csrf
                        <label for="assigned_to" class="mbui-label">Assigned researcher</label>
                        <div class="mt-1 flex gap-2">
                            <select id="assigned_to" name="assigned_to" class="mbui-input">
                                <option value="">Nobody yet</option>
                                @foreach ($people as $p)
                                    <option value="{{ $p->id }}" @selected((int) $rr->assigned_to === $p->id)>{{ $p->name }}{{ $p->job_title ? ' — '.$p->job_title : '' }}</option>
                                @endforeach
                            </select>
                            <x-mbui.button type="submit" variant="secondary">Save</x-mbui.button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.research-requests.details', $rr) }}" class="mt-4 grid grid-cols-2 gap-2">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="priority" class="mbui-label">Priority</label>
                            <select id="priority" name="priority" class="mbui-input mt-1">
                                @foreach (\App\Models\ResearchRequest::PRIORITIES as $k => $l)
                                    <option value="{{ $k }}" @selected($rr->priority === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="deadline" class="mbui-label">Deadline</label>
                            <input id="deadline" type="date" name="deadline" value="{{ $rr->deadline?->toDateString() }}" class="mbui-input mt-1">
                        </div>
                        <x-mbui.button type="submit" variant="secondary" class="col-span-2">Save details</x-mbui.button>
                    </form>
                @else
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="text-xs text-gray-500">Assigned</dt><dd class="text-gray-900">{{ $rr->assignee?->name ?? 'Nobody yet' }}</dd></div>
                    </dl>
                @endif
            </section>

            {{-- Customer --}}
            <section class="mbui-card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Customer</h2>
                <div class="mt-3 flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700">{{ mb_strtoupper(mb_substr($rr->user?->name ?? '?', 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-900">{{ $rr->user?->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $rr->user?->email }}</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Member since {{ $rr->user?->created_at?->format('M Y') }} · sent {{ $rr->created_at->format('d M Y, H:i') }}</p>
                @if ($customerHistory->isNotEmpty())
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Other requests</p>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($customerHistory as $other)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('admin.research-requests.show', $other) }}" class="truncate text-indigo-600 hover:underline">{{ $other->reference }} · {{ $other->title }}</a>
                                <span class="shrink-0 text-xs text-gray-500">{{ $other->stateLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

</x-layouts.admin>
