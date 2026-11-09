<x-layouts.admin title="Team notes" header="Team notes">
@php
    $me = auth()->user();
    $colors = \App\Models\StickyNote::COLORS;
    $newId = (int) session('new_note');
@endphp

<style>
    .sn-wrap { --sn-ease: cubic-bezier(.2, .8, .2, 1); }

    /* Header */
    .sn-hero { position: relative; overflow: hidden; border-radius: 1rem; padding: 1.5rem; color: #fff;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #db2777 120%); box-shadow: 0 10px 30px -12px rgba(79, 70, 229, .55); }
    .sn-hero::after { content: ""; position: absolute; right: -40px; top: -40px; width: 180px; height: 180px; border-radius: 50%;
        background: rgba(255, 255, 255, .12); }
    .sn-hero-count { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border-radius: 999px;
        background: rgba(255, 255, 255, .18); font-size: .75rem; font-weight: 600; backdrop-filter: blur(4px); }
    .sn-new-btn { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1rem; border-radius: .75rem;
        background: #fff; color: #4338ca; font-weight: 700; font-size: .875rem; transition: transform .2s var(--sn-ease), box-shadow .2s; }
    .sn-new-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgba(0, 0, 0, .35); }
    .sn-new-btn svg { transition: transform .3s var(--sn-ease); }
    .sn-new-btn.is-open svg { transform: rotate(45deg); }

    /* Toolbar */
    .sn-search { width: 100%; border-radius: .75rem; border: 1px solid #e5e7eb; background: #fff; padding: .6rem .9rem .6rem 2.4rem; font-size: .875rem;
        transition: border-color .2s, box-shadow .2s; }
    .sn-search:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 4px rgba(129, 140, 248, .2); }
    .sn-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .75rem; border-radius: 999px; border: 1px solid #e5e7eb;
        background: #fff; font-size: .8rem; font-weight: 600; color: #4b5563; transition: all .2s var(--sn-ease); cursor: pointer; }
    .sn-chip:hover { transform: translateY(-1px); border-color: #c7d2fe; }
    .sn-chip.is-active { background: #eef2ff; border-color: #818cf8; color: #4338ca; }
    .sn-dot { width: .8rem; height: .8rem; border-radius: 999px; border: 1px solid rgba(0, 0, 0, .1); }

    /* Composer */
    .sn-composer { border-radius: 1rem; border: 1px solid #e5e7eb; background: #fff; padding: 1rem; box-shadow: 0 20px 40px -24px rgba(0, 0, 0, .35); }
    .sn-composer textarea { width: 100%; resize: vertical; min-height: 110px; border: 0; border-radius: .75rem; padding: .9rem 1rem;
        font-size: .95rem; line-height: 1.5; transition: background-color .3s; }
    .sn-composer textarea:focus { outline: none; box-shadow: inset 0 0 0 2px rgba(99, 102, 241, .35); }
    .sn-swatch { width: 1.9rem; height: 1.9rem; border-radius: 999px; border: 2px solid transparent; cursor: pointer;
        transition: transform .2s var(--sn-ease), box-shadow .2s; }
    .sn-swatch:hover { transform: scale(1.12); }
    .sn-swatch.is-on { box-shadow: 0 0 0 3px #fff, 0 0 0 5px #6366f1; transform: scale(1.08); }
    .sn-primary { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem 1rem; border-radius: .7rem; background: #4f46e5; color: #fff;
        font-weight: 700; font-size: .85rem; transition: background .2s, transform .2s var(--sn-ease); }
    .sn-primary:hover { background: #4338ca; transform: translateY(-1px); }
    .sn-primary:disabled { opacity: .5; transform: none; cursor: not-allowed; }

    /* Section titles */
    .sn-section { display: flex; align-items: center; gap: .5rem; margin: 1.75rem 0 .75rem; font-size: .75rem; font-weight: 700;
        letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
    .sn-section::after { content: ""; flex: 1; height: 1px; background: linear-gradient(90deg, #e5e7eb, transparent); }

    /* Board: masonry columns */
    .sn-board { column-gap: 1.1rem; columns: 1; }
    @media (min-width: 640px) { .sn-board { columns: 2; } }
    @media (min-width: 1024px) { .sn-board { columns: 3; } }
    @media (min-width: 1536px) { .sn-board { columns: 4; } }

    /* A note */
    .sn-note { position: relative; break-inside: avoid; margin-bottom: 1.1rem; border-radius: .9rem; padding: 1.15rem 1.1rem .8rem;
        border: 1px solid; box-shadow: 0 1px 2px rgba(0, 0, 0, .05), 0 8px 18px -12px rgba(0, 0, 0, .25);
        transform: rotate(var(--tilt, 0deg)); transition: transform .25s var(--sn-ease), box-shadow .25s, opacity .25s;
        animation: sn-in .5s var(--sn-ease) backwards; animation-delay: var(--delay, 0ms); }
    .sn-note:nth-child(3n+1) { --tilt: -.6deg; }
    .sn-note:nth-child(3n+2) { --tilt: .5deg; }
    .sn-note:hover { transform: translateY(-4px) rotate(0deg); box-shadow: 0 2px 4px rgba(0, 0, 0, .06), 0 18px 30px -14px rgba(0, 0, 0, .35); z-index: 1; }
    .sn-note::before { content: ""; position: absolute; top: -9px; left: 50%; width: 64px; height: 18px; transform: translateX(-50%) rotate(-2deg);
        background: rgba(255, 255, 255, .55); border: 1px solid rgba(0, 0, 0, .06); border-radius: 3px; box-shadow: 0 1px 2px rgba(0, 0, 0, .06); }
    .sn-note.is-new { animation: sn-pop .8s var(--sn-ease) backwards; }
    .sn-note.is-leaving { opacity: 0; transform: scale(.9) rotate(-3deg); }
    .sn-pin { position: absolute; top: .55rem; right: .7rem; font-size: 1rem; animation: sn-drop .45s var(--sn-ease) both; }
    .sn-body { white-space: pre-line; overflow-wrap: anywhere; font-size: .925rem; line-height: 1.55; color: #1f2937; padding-right: 1rem; }
    .sn-body a { color: #4338ca; text-decoration: underline; }
    .sn-foot { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: .9rem; padding-top: .6rem;
        border-top: 1px dashed rgba(0, 0, 0, .12); font-size: .72rem; color: #4b5563; }
    .sn-avatar { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 999px;
        background: rgba(255, 255, 255, .7); font-weight: 700; font-size: .7rem; color: #374151; border: 1px solid rgba(0, 0, 0, .08); }
    .sn-actions { display: flex; gap: .15rem; opacity: 0; transform: translateY(3px); transition: opacity .2s, transform .2s var(--sn-ease); }
    .sn-note:hover .sn-actions, .sn-note:focus-within .sn-actions { opacity: 1; transform: none; }
    @media (hover: none) { .sn-actions { opacity: 1; transform: none; } }
    .sn-act { padding: .3rem; border-radius: .45rem; color: #4b5563; transition: background .15s, color .15s, transform .15s; }
    .sn-act:hover { background: rgba(255, 255, 255, .7); color: #111827; transform: scale(1.08); }
    .sn-act.is-danger:hover { color: #b91c1c; }
    .sn-edit textarea { width: 100%; border: 0; border-radius: .6rem; padding: .6rem .7rem; background: rgba(255, 255, 255, .7); font-size: .9rem; min-height: 110px; }
    .sn-edit textarea:focus { outline: none; box-shadow: inset 0 0 0 2px rgba(99, 102, 241, .35); }

    /* Empty state */
    .sn-empty { text-align: center; padding: 3.5rem 1rem; border: 2px dashed #e5e7eb; border-radius: 1rem; background: #fafafa; }
    .sn-empty-icon { font-size: 2.6rem; display: inline-block; animation: sn-float 3s ease-in-out infinite; }

    /* Toast */
    .sn-toast { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 60; padding: .7rem 1rem; border-radius: .8rem; background: #111827; color: #fff;
        font-size: .85rem; font-weight: 600; box-shadow: 0 12px 30px -10px rgba(0, 0, 0, .5); }

    @keyframes sn-in { from { opacity: 0; transform: translateY(14px) scale(.97) rotate(var(--tilt, 0deg)); } to { opacity: 1; transform: rotate(var(--tilt, 0deg)); } }
    @keyframes sn-pop { 0% { opacity: 0; transform: scale(.6) rotate(-6deg); } 60% { opacity: 1; transform: scale(1.04) rotate(1deg); }
        100% { transform: rotate(var(--tilt, 0deg)); box-shadow: 0 0 0 4px rgba(99, 102, 241, .35), 0 18px 30px -14px rgba(0, 0, 0, .35); } }
    @keyframes sn-drop { from { opacity: 0; transform: translateY(-10px) rotate(-30deg); } to { opacity: 1; transform: none; } }
    @keyframes sn-float { 0%, 100% { transform: translateY(0) rotate(-4deg); } 50% { transform: translateY(-8px) rotate(4deg); } }

    @media (prefers-reduced-motion: reduce) {
        .sn-note, .sn-note.is-new, .sn-pin, .sn-empty-icon { animation: none !important; }
        .sn-note, .sn-note:hover { transform: none; transition: none; }
    }
</style>

<div class="sn-wrap"
     x-data="{
        q: '', color: '', composing: {{ $errors->any() ? 'true' : 'false' }}, toast: @js(session('success')),
        show(el) {
            const q = this.q.trim().toLowerCase();
            return (!this.color || el.dataset.color === this.color) && (!q || el.dataset.text.includes(q));
        },
     }"
     x-init="if (toast) setTimeout(() => toast = null, 2600)">

    {{-- Header --}}
    <div class="sn-hero">
        <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold">Team notes</h1>
                    <span class="sn-hero-count">{{ $total }} {{ \Illuminate\Support\Str::plural('note', $total) }}</span>
                </div>
                <p class="mt-1 text-sm text-white/80">Reminders and info for every admin and helpdesk member. Passwords go in the Password vault, never here.</p>
            </div>
            <button type="button" class="sn-new-btn" :class="composing && 'is-open'"
                    @click="composing = !composing; if (composing) $nextTick(() => $refs.newBody.focus())">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span x-text="composing ? 'Close' : 'New note'">New note</span>
            </button>
        </div>
    </div>

    {{-- Composer --}}
    <div x-show="composing" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-3 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         class="mt-4">
        <form method="POST" action="{{ route('admin.sticky-notes.store') }}" class="sn-composer"
              x-data="{ c: @js(old('color', 'yellow')), body: @js(old('body', '')), palette: @js(collect($colors)->map(fn ($p) => $p[0])) }"
              @keydown.ctrl.enter="$el.requestSubmit()" @keydown.meta.enter="$el.requestSubmit()">
            @csrf
            <textarea name="body" x-ref="newBody" x-model="body" maxlength="1000" required placeholder="What should the team know? (Ctrl + Enter to save)"
                      :style="'background-color:' + palette[c]"></textarea>
            <x-input-error :messages="$errors->get('body')" class="mt-2" />
            <input type="hidden" name="color" :value="c">
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    @foreach ($colors as $name => [$bg, $border])
                        <button type="button" class="sn-swatch" :class="c === '{{ $name }}' && 'is-on'" style="background: {{ $bg }}; border-color: {{ $border }}"
                                @click="c = '{{ $name }}'" title="{{ ucfirst($name) }}" aria-label="{{ ucfirst($name) }}"></button>
                    @endforeach
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs tabular-nums text-gray-400" x-text="body.length + ' / 1000'"></span>
                    <button type="submit" class="sn-primary" :disabled="!body.trim()">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Stick it
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Toolbar --}}
    @if ($total > 0)
        <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative lg:w-80">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                <input type="search" x-model="q" placeholder="Search notes or names…" class="sn-search">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="sn-chip" :class="!color && 'is-active'" @click="color = ''">All</button>
                @foreach ($colors as $name => [$bg, $border])
                    <button type="button" class="sn-chip" :class="color === '{{ $name }}' && 'is-active'" @click="color = color === '{{ $name }}' ? '' : '{{ $name }}'">
                        <span class="sn-dot" style="background: {{ $bg }}; border-color: {{ $border }}"></span>{{ ucfirst($name) }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Notes --}}
    @if ($total === 0)
        <div class="sn-empty mt-6">
            <span class="sn-empty-icon">🗒️</span>
            <h2 class="mt-3 text-lg font-semibold text-gray-900">No notes yet</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">Stick the first one: today's priorities, a number to call back, a reminder for the next shift…</p>
            <button type="button" class="sn-primary mt-5" @click="composing = true; $nextTick(() => $refs.newBody.focus())">Write a note</button>
        </div>
    @else
        @foreach (['Pinned' => $pinned, 'Notes' => $others] as $label => $list)
            @continue($list->isEmpty())
            <div class="sn-section">
                @if ($label === 'Pinned') <span>📌</span> @endif
                {{ $label }} <span class="text-gray-400">· {{ $list->count() }}</span>
            </div>
            <div class="sn-board">
                @foreach ($list as $i => $note)
                    @php
                        [$bg, $border] = $note->palette();
                        $author = $note->creator?->name ?? 'Someone';
                        $mine = $note->canBeChangedBy($me);
                    @endphp
                    <article class="sn-note {{ $note->id === $newId ? 'is-new' : '' }}"
                             style="background: {{ $bg }}; border-color: {{ $border }}; --delay: {{ min($i, 12) * 45 }}ms"
                             data-color="{{ $note->color }}" data-text="{{ \Illuminate\Support\Str::lower($note->body.' '.$author) }}"
                             x-data="{ editing: false, leaving: false }" x-show="show($el)" x-transition.opacity.duration.200ms
                             :class="leaving && 'is-leaving'">
                        @if ($note->pinned)
                            <span class="sn-pin" title="Pinned">📌</span>
                        @endif

                        <div x-show="!editing" class="sn-body">{!! \App\Support\StickyNoteText::render($note->body) !!}</div>

                        @if ($mine)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('admin.sticky-notes.update', $note) }}" class="sn-edit"
                                  x-data="{ c: @js($note->color) }" @keydown.ctrl.enter="$el.requestSubmit()" @keydown.escape="editing = false">
                                @csrf @method('PUT')
                                <textarea name="body" maxlength="1000" required x-ref="edit">{{ $note->body }}</textarea>
                                <input type="hidden" name="color" :value="c">
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        @foreach ($colors as $name => [$cbg, $cborder])
                                            <button type="button" class="sn-swatch" style="width:1.35rem;height:1.35rem;background: {{ $cbg }}; border-color: {{ $cborder }}"
                                                    :class="c === '{{ $name }}' && 'is-on'" @click="c = '{{ $name }}'" aria-label="{{ ucfirst($name) }}"></button>
                                        @endforeach
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="editing = false" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Cancel</button>
                                        <button type="submit" class="sn-primary" style="padding:.35rem .75rem;font-size:.75rem">Save</button>
                                    </div>
                                </div>
                            </form>
                        @endif

                        <div class="sn-foot" x-show="!editing">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="sn-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($author, 0, 1)) }}</span>
                                <span class="truncate" title="{{ $note->updated_at?->format('d M Y H:i') }}">
                                    <strong class="font-semibold">{{ $author }}</strong> · {{ $note->updated_at?->diffForHumans(short: true) }}
                                    @if ($note->editor && $note->updated_by !== $note->created_by) <span class="text-gray-500">(edited by {{ $note->editor->name }})</span> @endif
                                </span>
                            </div>
                            @if ($mine)
                                <div class="sn-actions">
                                    <form method="POST" action="{{ route('admin.sticky-notes.pin', $note) }}">
                                        @csrf
                                        <button type="submit" class="sn-act" title="{{ $note->pinned ? 'Unpin' : 'Pin to top' }}" aria-label="{{ $note->pinned ? 'Unpin' : 'Pin' }}">
                                            <svg class="h-4 w-4" fill="{{ $note->pinned ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75l3.75 3.75-3 1.5-3.75 3.75.75 4.5-1.5 1.5-3.75-3.75L4.5 19.5l-.75-.75 4.5-4.5-3.75-3.75 1.5-1.5 4.5.75L14.25 6l1.5-3z" /></svg>
                                        </button>
                                    </form>
                                    <button type="button" class="sn-act" title="Edit" aria-label="Edit" @click="editing = true; $nextTick(() => $refs.edit.focus())">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                    </button>
                                    <form method="POST" action="{{ route('admin.sticky-notes.destroy', $note) }}" x-ref="del"
                                          @submit.prevent="if (confirm('Remove this note?')) { leaving = true; setTimeout(() => $refs.del.submit(), 250) }">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="sn-act is-danger" title="Delete" aria-label="Delete">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endforeach
    @endif

    {{-- Toast --}}
    <div x-show="toast" x-cloak class="sn-toast"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-3">
        <span x-text="toast"></span>
    </div>
</div>
</x-layouts.admin>
