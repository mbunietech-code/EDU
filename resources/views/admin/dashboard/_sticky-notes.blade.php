{{-- Team sticky notes: every admin and helpdesk member reads and adds; the author or a super admin edits. --}}
@php
    $me = auth()->user();
    $colors = \App\Models\StickyNote::COLORS;
@endphp

<section id="notes" class="mt-6" x-data="{ adding: false }">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="mbui-section-label">Sticky notes</h2>
            <p class="mt-1 text-sm text-gray-500">Seen by every admin and helpdesk member. Never put passwords here: use the Password vault.</p>
        </div>
        <button type="button" @click="adding = !adding" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            <span x-text="adding ? 'Close' : 'Add note'">Add note</span>
        </button>
    </div>

    <form x-show="adding" x-cloak method="POST" action="{{ route('admin.sticky-notes.store') }}" class="mbui-card mt-3 p-4" x-data="{ color: 'yellow' }">
        @csrf
        <textarea name="body" rows="3" maxlength="1000" required placeholder="Write a note for the team…" class="mbui-input w-full">{{ old('body') }}</textarea>
        <x-input-error :messages="$errors->get('body')" class="mt-2" />
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                @foreach ($colors as $name => [$bg, $border])
                    <label class="cursor-pointer" title="{{ ucfirst($name) }}">
                        <input type="radio" name="color" value="{{ $name }}" x-model="color" class="sr-only">
                        <span class="block h-7 w-7 rounded-full border-2" style="background: {{ $bg }}; border-color: {{ $border }}"
                              :style="color === '{{ $name }}' ? 'background: {{ $bg }}; border-color: #4f46e5; box-shadow: 0 0 0 2px #c7d2fe' : 'background: {{ $bg }}; border-color: {{ $border }}'"></span>
                    </label>
                @endforeach
            </div>
            <x-mbui.button type="submit">Save note</x-mbui.button>
        </div>
    </form>

    @if ($stickyNotes->isEmpty())
        <p class="mt-3 rounded-lg border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500">No notes yet. Add reminders, numbers to call, or what the team should know today.</p>
    @else
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($stickyNotes as $note)
                @php([$bg, $border] = $note->palette())
                <div class="relative flex flex-col rounded-xl border p-4 shadow-sm" style="background: {{ $bg }}; border-color: {{ $border }}" x-data="{ editing: false }">
                    @if ($note->pinned)
                        <span class="absolute right-3 top-3 text-xs font-semibold text-gray-600" title="Pinned">📌</span>
                    @endif

                    <div x-show="!editing" class="flex-1 whitespace-pre-line break-words pr-6 text-sm text-gray-900">{{ $note->body }}</div>

                    @if ($note->canBeChangedBy($me))
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.sticky-notes.update', $note) }}" class="flex-1">
                            @csrf @method('PUT')
                            <textarea name="body" rows="4" maxlength="1000" required class="mbui-input w-full bg-white/70 text-sm">{{ $note->body }}</textarea>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <select name="color" class="mbui-input py-1 text-sm">
                                    @foreach ($colors as $name => $c)
                                        <option value="{{ $name }}" @selected($note->color === $name)>{{ ucfirst($name) }}</option>
                                    @endforeach
                                </select>
                                <div class="flex gap-2">
                                    <button type="button" @click="editing = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                                    <x-mbui.button type="submit">Save</x-mbui.button>
                                </div>
                            </div>
                        </form>
                    @endif

                    <div class="mt-3 flex items-center justify-between gap-2 border-t pt-2 text-xs text-gray-600" style="border-color: {{ $border }}">
                        <span title="{{ $note->updated_at?->format('d M Y H:i') }}">
                            {{ $note->creator?->name ?? 'Someone' }} · {{ $note->updated_at?->diffForHumans() }}
                        </span>
                        @if ($note->canBeChangedBy($me))
                            <div class="flex items-center gap-3" x-show="!editing">
                                <form method="POST" action="{{ route('admin.sticky-notes.pin', $note) }}">
                                    @csrf
                                    <button type="submit" class="font-medium hover:text-gray-900">{{ $note->pinned ? 'Unpin' : 'Pin' }}</button>
                                </form>
                                <button type="button" @click="editing = true" class="font-medium hover:text-gray-900">Edit</button>
                                <form method="POST" action="{{ route('admin.sticky-notes.destroy', $note) }}" onsubmit="return confirm('Remove this note?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="font-medium text-red-700 hover:text-red-900">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
