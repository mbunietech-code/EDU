<x-layouts.user :title="$chapter->title . ' — ' . $research->title">

    <div x-data="researchReader({{ $research->id }})" class="lg:grid lg:grid-cols-[16rem_1fr] lg:gap-8">

        {{-- Contents sidebar --}}
        <aside class="mb-6 lg:mb-0">
            <div class="lg:sticky lg:top-6">
                <a href="{{ route('research.show', $research) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">&larr; {{ $research->title }}</a>
                <h2 class="mbui-section-label mt-3">Contents</h2>
                <nav class="mt-3 space-y-3 text-sm">
                    @foreach ($research->chapters as $ch)
                        <div>
                            <a href="{{ route('research.read', [$research, $ch]) }}"
                               class="block font-semibold {{ $ch->id === $chapter->id ? 'text-indigo-600' : 'text-gray-800 hover:text-indigo-600' }}">
                                {{ $ch->title }}
                            </a>
                            @if ($ch->id === $chapter->id && $chapter->sections->isNotEmpty())
                                <ul class="mt-1 space-y-1 border-l border-gray-200 pl-3">
                                    @foreach ($chapter->sections as $section)
                                        <li>
                                            <a href="#section-{{ $section->id }}" class="flex items-start gap-1.5 text-xs text-gray-500 hover:text-indigo-600">
                                                <span x-show="done.includes({{ $section->id }})" class="text-emerald-500">&check;</span>
                                                <span>{{ $section->heading }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Reading pane --}}
        <article class="min-w-0">
            <p class="text-sm font-medium text-indigo-600">{{ $research->category->name ?? 'Research' }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">{{ $chapter->title }}</h1>

            @forelse ($chapter->sections as $section)
                <section id="section-{{ $section->id }}" class="mt-8 scroll-mt-24 border-t border-gray-100 pt-6 first:mt-6 first:border-0 first:pt-0">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-lg font-bold text-gray-900">{{ $section->heading }}</h2>
                        <label class="mt-1 flex shrink-0 cursor-pointer items-center gap-1.5 text-xs text-gray-500">
                            <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   :checked="done.includes({{ $section->id }})"
                                   @change="toggle({{ $section->id }}, $event.target.checked)">
                            Mark read
                        </label>
                    </div>
                    <div class="research-prose mt-3">
                        {!! $section->bodyHtml() !!}
                    </div>
                </section>
            @empty
                <p class="mt-8 text-sm text-gray-400">This chapter has no sections yet.</p>
            @endforelse

            {{-- Prev / Next --}}
            <div class="mt-12 flex items-center justify-between border-t border-gray-200 pt-6 text-sm">
                @if ($prev)
                    <a href="{{ route('research.read', [$research, $prev]) }}" class="inline-flex items-center gap-1.5 font-medium text-gray-700 hover:text-indigo-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                        <span class="max-w-[10rem] truncate">{{ $prev->title }}</span>
                    </a>
                @else <span></span> @endif

                @if ($next)
                    <a href="{{ route('research.read', [$research, $next]) }}" class="inline-flex items-center gap-1.5 font-medium text-gray-700 hover:text-indigo-600">
                        <span class="max-w-[10rem] truncate">{{ $next->title }}</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                @else <span></span> @endif
            </div>
        </article>
    </div>

    @push('scripts')
    <script>
        function researchReader(researchId) {
            return {
                done: @json($progress->done_section_ids ?? []),
                toggle(sectionId, isDone) {
                    if (isDone) { if (!this.done.includes(sectionId)) this.done.push(sectionId); }
                    else { this.done = this.done.filter(id => id !== sectionId); }
                    fetch(`{{ url('research') }}/{{ $research->slug }}/progress`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ section_id: sectionId, done: isDone }),
                    }).catch(() => {});
                },
            };
        }
    </script>
    @endpush
</x-layouts.user>
