<x-layouts.user :title="$rr->reference.' · '.$rr->title" header="Research & Consultancy">

    <div class="mbui-page-header">
        <div class="min-w-0">
            <a href="{{ route('user.research-requests.index') }}" class="mbui-anchor text-sm">&larr; My requests</a>
            <p class="mt-2 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $rr->reference }} · {{ $rr->typeLabel() }}</p>
            <h1 class="mbui-title mt-1 [overflow-wrap:anywhere]">{{ $rr->title }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <x-mbui.badge :appearance="$rr->priorityAppearance()">{{ $rr->priorityLabel() }} priority</x-mbui.badge>
                @if (! $rr->isActive())
                    <x-mbui.badge :appearance="$rr->status === 'completed' ? 'success' : 'neutral'">{{ $rr->statusLabel() }}</x-mbui.badge>
                @endif
                @if ($rr->deadline)
                    <span @class(['text-gray-600', '!font-semibold !text-red-600' => $rr->isOverdue(), '!font-semibold !text-amber-600' => $rr->isDueSoon()])>
                        Deadline {{ $rr->deadline->format('D, d M Y') }} @if ($rr->isActive()) · {{ $rr->deadlineLabel() }} @endif
                    </span>
                @endif
            </div>
        </div>
        @if ($rr->canBeCancelledByCustomer())
            <div x-data="{ open: false }" class="shrink-0">
                <x-mbui.button variant="secondary" class="text-red-600" @click="open = true">Cancel request</x-mbui.button>
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @keydown.escape.window="open = false">
                    <form method="POST" action="{{ route('user.research-requests.cancel', $rr) }}" class="mbui-card w-full max-w-md p-6" @click.outside="open = false">
                        @csrf
                        <h2 class="text-base font-semibold text-gray-900">Cancel this request?</h2>
                        <p class="mt-1 text-sm text-gray-500">The team will stop working on it. You can always send a new request.</p>
                        <label for="cancel_reason" class="mbui-label mt-4">Reason <span class="font-normal text-gray-400">(optional)</span></label>
                        <textarea id="cancel_reason" name="reason" rows="3" maxlength="500" class="mbui-input mt-1"></textarea>
                        <div class="mt-4 flex justify-end gap-2">
                            <x-mbui.button variant="secondary" @click="open = false">Keep it</x-mbui.button>
                            <x-mbui.button type="submit" variant="danger">Cancel request</x-mbui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- Things waiting for the customer --}}
    @if ($rr->hasPendingQuote())
        <div class="mt-6 overflow-hidden rounded-xl border border-amber-200 bg-amber-50" x-data="{ decline: false }">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Price for this work</p>
                    <p class="mt-1 text-3xl font-extrabold text-gray-900">{{ $rr->quoteLabel() }}</p>
                    @if (($rr->quote_currency ?: 'TZS') === 'TZS')
                        <x-currency-conversion :amount="$rr->quote_amount" class="mt-1 text-xs font-semibold text-gray-600" />
                    @endif
                    @if ($rr->quote_note)<p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $rr->quote_note }}</p>@endif
                </div>
                <div class="flex shrink-0 gap-2">
                    <form method="POST" action="{{ route('user.research-requests.quote.accept', $rr) }}">
                        @csrf
                        <x-mbui.button type="submit" variant="success">Accept price</x-mbui.button>
                    </form>
                    <x-mbui.button variant="secondary" @click="decline = ! decline">Decline</x-mbui.button>
                </div>
            </div>
            <form x-show="decline" x-cloak method="POST" action="{{ route('user.research-requests.quote.decline', $rr) }}" class="border-t border-amber-200 bg-white/60 p-5">
                @csrf
                <label for="decline_reason" class="mbui-label">Tell the team why (e.g. your budget) so they can adjust</label>
                <textarea id="decline_reason" name="reason" rows="2" maxlength="1000" class="mbui-input mt-1"></textarea>
                <div class="mt-2 flex justify-end"><x-mbui.button type="submit" variant="danger">Decline price</x-mbui.button></div>
            </form>
        </div>
    @endif

    @if ($rr->canBeCompletedByCustomer() || $rr->canRequestRevision())
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5" x-data="{ mode: null, rating: 0, hover: 0 }">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-gray-900">{{ $rr->canBeCompletedByCustomer() ? 'Your work is delivered' : 'A draft is ready for you' }}</p>
                    <p class="text-sm text-gray-600">Download it below. {{ $rr->canBeCompletedByCustomer() ? 'Happy with it? Accept and rate it — or ask for changes.' : 'Read it and tell us what to change.' }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    @if ($rr->canBeCompletedByCustomer())
                        <x-mbui.button variant="success" @click="mode = mode === 'accept' ? null : 'accept'">Accept &amp; rate</x-mbui.button>
                    @endif
                    @if ($rr->canRequestRevision())
                        <x-mbui.button variant="secondary" @click="mode = mode === 'revise' ? null : 'revise'">Ask for changes</x-mbui.button>
                    @endif
                </div>
            </div>

            @if ($rr->canBeCompletedByCustomer())
                <form x-show="mode === 'accept'" x-cloak method="POST" action="{{ route('user.research-requests.complete', $rr) }}" class="mt-4 border-t border-emerald-200 pt-4">
                    @csrf
                    <span class="mbui-label">How was the work?</span>
                    <div class="mt-1 flex items-center gap-1" @mouseleave="hover = 0">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button" @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                                class="p-0.5" :class="(hover || rating) >= {{ $i }} ? 'text-amber-400' : 'text-gray-300'">
                                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" /></svg>
                            </button>
                        @endfor
                        <span class="ml-2 text-sm text-gray-500" x-text="['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'][hover || rating]"></span>
                    </div>
                    <input type="hidden" name="rating" :value="rating">
                    <label for="review" class="mbui-label mt-3">Review <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea id="review" name="review" rows="3" maxlength="3000" class="mbui-input mt-1" placeholder="What went well? What could be better?"></textarea>
                    <div class="mt-3 flex justify-end">
                        <x-mbui.button type="submit" variant="success" ::disabled="rating === 0" class="disabled:opacity-50">Accept &amp; close request</x-mbui.button>
                    </div>
                </form>
            @endif

            @if ($rr->canRequestRevision())
                <form x-show="mode === 'revise'" x-cloak method="POST" action="{{ route('user.research-requests.revision', $rr) }}" enctype="multipart/form-data" class="mt-4 border-t border-emerald-200 pt-4">
                    @csrf
                    <label for="revision_reason" class="mbui-label">What should be changed?</label>
                    <textarea id="revision_reason" name="reason" rows="4" required minlength="10" maxlength="5000" class="mbui-input mt-1" placeholder="List the changes: chapter / page, what is wrong, what you expect…"></textarea>
                    <input type="file" name="files[]" multiple class="mt-2 block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">
                    <div class="mt-3 flex justify-end"><x-mbui.button type="submit">Send revision request</x-mbui.button></div>
                </form>
            @endif
        </div>
    @endif

    @if ($rr->status === 'completed' && $rr->rating)
        <div class="mt-6 flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm">
            <x-research-requests.stars :rating="$rr->rating" />
            <span class="text-gray-600">You rated this work {{ $rr->rating }}/5 @if ($rr->review) — “{{ \Illuminate\Support\Str::limit($rr->review, 140) }}” @endif</span>
        </div>
    @endif

    @if ($rr->status === 'cancelled')
        <x-mbui.alert type="warning" class="mt-6">This request was cancelled{{ $rr->cancelled_at ? ' on '.$rr->cancelled_at->format('d M Y') : '' }}.@if ($rr->cancel_reason) Reason: {{ $rr->cancel_reason }}@endif</x-mbui.alert>
    @endif

    <x-research-requests.tracker :tracker="$tracker" :rr="$rr" class="mt-6" />

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Deliverables --}}
            <section class="mbui-card p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Your work (drafts &amp; final)</h2>
                    <span class="text-xs text-gray-500">{{ $deliverables->count() }} file{{ $deliverables->count() === 1 ? '' : 's' }}</span>
                </div>
                @if ($deliverables->isEmpty())
                    <p class="mt-3 rounded-lg bg-gray-50 p-4 text-center text-sm text-gray-500">Drafts and the final work will appear here to download.</p>
                @else
                    <div class="mt-3 space-y-4">
                        @foreach (['final' => 'Final work', 'draft' => 'Drafts'] as $kind => $label)
                            @php($group = $deliverables->where('kind', $kind))
                            @if ($group->isNotEmpty())
                                <div>
                                    <p @class(['mb-2 text-xs font-semibold uppercase tracking-wider', 'text-emerald-700' => $kind === 'final', 'text-sky-700' => $kind === 'draft'])>{{ $label }}</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($group as $file)
                                            <x-research-requests.file-link :file="$file" :href="route('user.research-requests.file', [$rr, $file])" />
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Messages --}}
            <section id="messages" class="mbui-card p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900">Messages with the team</h2>
                <div class="mt-4 max-h-[32rem] space-y-3 overflow-y-auto pr-1" x-data x-init="$el.scrollTop = $el.scrollHeight">
                    @forelse ($rr->messages as $msg)
                        @php($mine = ! $msg->is_from_admin)
                        <div @class(['flex', 'justify-end' => $mine])>
                            <div @class(['max-w-[85%] rounded-2xl px-4 py-2.5', 'rounded-br-sm bg-indigo-600 text-white' => $mine, 'rounded-bl-sm bg-gray-100 text-gray-900' => ! $mine])>
                                <p @class(['text-xs', 'text-indigo-100' => $mine, 'text-gray-500' => ! $mine])>
                                    {{ $mine ? 'You' : ($msg->user?->name ?? 'MbunieEduHub team').' · Team' }} · {{ $msg->created_at->diffForHumans() }}
                                </p>
                                @if ($msg->body)<p class="mt-0.5 whitespace-pre-line text-sm [overflow-wrap:anywhere]">{{ $msg->body }}</p>@endif
                                @foreach ($msg->files as $file)
                                    <a href="{{ route('user.research-requests.file', [$rr, $file]) }}" @class(['mt-1.5 flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium', 'bg-white/15 hover:bg-white/25' => $mine, 'bg-white hover:bg-gray-50' => ! $mine])>
                                        <x-research-requests.icon name="paperclip" class="h-3.5 w-3.5" /> <span class="truncate">{{ $file->original_name }}</span><span class="shrink-0 opacity-70">{{ $file->sizeLabel() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-center text-sm text-gray-500">No messages yet. Ask a question or add details for the team.</p>
                    @endforelse
                </div>

                @if ($rr->isActive())
                    <form method="POST" action="{{ route('user.research-requests.message', $rr) }}" enctype="multipart/form-data" class="mt-4 border-t border-gray-100 pt-4" x-data="{ n: 0 }">
                        @csrf
                        <label for="body" class="sr-only">Message</label>
                        <textarea id="body" name="body" rows="3" maxlength="5000" class="mbui-input" placeholder="Write to the team…">{{ old('body') }}</textarea>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-600 hover:text-indigo-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                <span x-text="n ? n + ' file(s) attached' : 'Attach files'"></span>
                                <input type="file" name="files[]" multiple class="sr-only" @change="n = $event.target.files.length">
                            </label>
                            <x-mbui.button type="submit">Send</x-mbui.button>
                        </div>
                    </form>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            {{-- Details --}}
            <section class="mbui-card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Request details</h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                    <div><dt class="text-xs text-gray-500">Level</dt><dd class="font-medium text-gray-900">{{ $rr->levelLabel() }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Field</dt><dd class="font-medium text-gray-900">{{ $rr->field_of_study ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Length</dt><dd class="font-medium text-gray-900">{{ $rr->pages ? $rr->pages.' pages' : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Citation</dt><dd class="font-medium text-gray-900">{{ $rr->citationLabel() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Your budget</dt><dd class="font-medium text-gray-900">{{ $rr->budgetLabel() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Price</dt><dd class="font-medium text-gray-900">{{ $rr->quoteLabel() ?? 'Not quoted yet' }}@if ($rr->quote_status && $rr->quote_status !== 'pending') <span class="text-xs text-gray-500">({{ $rr->quote_status }})</span>@endif</dd></div>
                    @if ($rr->purpose)<div class="col-span-2"><dt class="text-xs text-gray-500">Purpose</dt><dd class="text-gray-900">{{ $rr->purpose }}</dd></div>@endif
                    <div class="col-span-2"><dt class="text-xs text-gray-500">Handled by</dt><dd class="font-medium text-gray-900">{{ $rr->assignee?->name ?? 'Our research team' }}@if ($rr->assignee?->job_title) <span class="text-xs font-normal text-gray-500">· {{ $rr->assignee->job_title }}</span>@endif</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-gray-500">Sent</dt><dd class="text-gray-900">{{ $rr->created_at->format('d M Y, H:i') }}</dd></div>
                </dl>
                <details class="group mt-4 border-t border-gray-100 pt-3">
                    <summary class="cursor-pointer text-sm font-medium text-indigo-600">Your description</summary>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 [overflow-wrap:anywhere]">{{ $rr->description }}</p>
                </details>
                @if ($briefFiles->isNotEmpty())
                    <div class="mt-3 space-y-2 border-t border-gray-100 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Your attachments</p>
                        @foreach ($briefFiles as $file)
                            <x-research-requests.file-link :file="$file" :href="route('user.research-requests.file', [$rr, $file])" />
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- History --}}
            <section class="mbui-card p-5">
                <h2 class="text-sm font-semibold text-gray-900">History</h2>
                <ol class="relative mt-4 space-y-4 border-l border-gray-200 pl-5" role="list">
                    @foreach ($rr->events as $event)
                        <li class="relative">
                            <span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full ring-4 ring-white" style="background-color: {{ $event->toStage?->hex() ?? '#9CA3AF' }}"></span>
                            <p class="text-sm font-medium text-gray-900">{{ $event->label() }}@if ($event->progress !== null && in_array($event->type, ['stage', 'progress'], true)) <span class="font-normal text-gray-500">· {{ $event->progress }}%</span>@endif</p>
                            @if ($event->note)<p class="mt-0.5 whitespace-pre-line text-sm text-gray-600 [overflow-wrap:anywhere]">{{ $event->note }}</p>@endif
                            <p class="text-xs text-gray-400">{{ $event->created_at->format('d M Y, H:i') }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

</x-layouts.user>
