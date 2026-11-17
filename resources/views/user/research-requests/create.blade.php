<x-layouts.user title="New research request" header="Research requests">

    <div class="mbui-page-header">
        <div>
            <a href="{{ route('user.research-requests.index') }}" class="mbui-anchor text-sm">&larr; My requests</a>
            <h1 class="mbui-title mt-1">Ask for research work</h1>
            <p class="mt-1 text-sm text-gray-500">Tell us what you need. The clearer the brief, the faster we can price it and start.</p>
        </div>
    </div>

    @php
        $typeIcons = [
            'thesis' => 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342',
            'proposal' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
            'data_analysis' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
            'editing' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z',
        ];
        $defaultIcon = 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z';
    @endphp

    <form method="POST" action="{{ route('user.research-requests.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-3"
        x-data="{
            type: @js(old('research_type', $preset)),
            priority: @js(old('priority', 'normal')),
            desc: @js(old('description', '')),
            files: [],
            sending: false,
            pick(e) { this.files = Array.from(e.target.files).map(f => ({ name: f.name, size: f.size })) },
            size(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB' },
        }" @submit="sending = true">
        @csrf

        <div class="space-y-6 lg:col-span-2">
            {{-- 1. What --}}
            <section class="mbui-card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">1</span>
                    <h2 class="text-base font-semibold text-gray-900">What kind of work?</h2>
                </div>
                <fieldset class="mt-4">
                    <legend class="sr-only">Type of research work</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (\App\Models\ResearchRequest::TYPES as $key => $label)
                            <label class="relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                                :class="type === '{{ $key }}' ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'">
                                <input type="radio" name="research_type" value="{{ $key }}" x-model="type" class="sr-only" required>
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="type === '{{ $key }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500'">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $typeIcons[$key] ?? $defaultIcon }}" /></svg>
                                </span>
                                <span class="text-sm font-medium text-gray-900">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('research_type')" class="mt-2" />
                </fieldset>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="title" class="mbui-label">Title / topic</label>
                        <input id="title" name="title" value="{{ old('title') }}" required minlength="5" maxlength="200" class="mbui-input mt-1" placeholder="e.g. Effect of mobile money on small businesses in Arusha">
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <label for="field_of_study" class="mbui-label">Field / subject area</label>
                        <input id="field_of_study" name="field_of_study" value="{{ old('field_of_study') }}" maxlength="120" class="mbui-input mt-1" placeholder="e.g. Economics, Public health">
                    </div>
                    <div>
                        <label for="academic_level" class="mbui-label">Academic level</label>
                        <select id="academic_level" name="academic_level" required class="mbui-input mt-1">
                            <option value="">Choose…</option>
                            @foreach (\App\Models\ResearchRequest::LEVELS as $key => $label)
                                <option value="{{ $key }}" @selected(old('academic_level') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('academic_level')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="purpose" class="mbui-label">Purpose <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="purpose" name="purpose" value="{{ old('purpose') }}" maxlength="200" class="mbui-input mt-1" placeholder="e.g. Final year project at UDSM, journal submission, NGO baseline report">
                    </div>
                </div>
            </section>

            {{-- 2. Describe --}}
            <section class="mbui-card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">2</span>
                    <h2 class="text-base font-semibold text-gray-900">Describe the work</h2>
                </div>
                <div class="mt-4">
                    <label for="description" class="mbui-label">Detailed description</label>
                    <textarea id="description" name="description" rows="9" required minlength="30" maxlength="20000" x-model="desc" x-ref="desc" class="mbui-input mt-1"
                        placeholder="Explain what you need: the research question or objectives, chapters or sections, methods / software, data you already have, your supervisor's instructions, anything the team must follow…"></textarea>
                    <div class="mt-1 flex justify-between text-xs">
                        <x-input-error :messages="$errors->get('description')" />
                        <span class="ml-auto" :class="desc.length < 30 ? 'text-amber-600' : 'text-gray-400'" x-text="desc.length < 30 ? (30 - desc.length) + ' more characters needed' : desc.length + ' characters'"></span>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="text-gray-500">Good briefs mention:</span>
                    @foreach (['Objectives', 'Chapters needed', 'Methodology', 'Software (SPSS, R…)', 'Supervisor notes', 'Sources to use'] as $hint)
                        <button type="button" class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700 hover:bg-indigo-100 hover:text-indigo-700"
                            @click="desc = (desc ? desc.trimEnd() + '\n' : '') + '{{ $hint }}: '; $nextTick(() => $refs.desc?.focus())">+ {{ $hint }}</button>
                    @endforeach
                </div>

                <div class="mt-5">
                    <span class="mbui-label">Attachments <span class="font-normal text-gray-400">(guidelines, data, drafts — optional)</span></span>
                    <label for="files" class="mt-1 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 px-4 py-6 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" /></svg>
                        <span class="mt-2 text-sm font-medium text-indigo-600">Choose files</span>
                        <span class="mt-0.5 text-xs text-gray-500">Up to {{ \App\Models\ResearchRequestFile::MAX_FILES }} files, {{ \App\Models\ResearchRequestFile::MAX_KB / 1024 }} MB each · PDF, Word, Excel, PowerPoint, images, ZIP, SPSS/Stata data</span>
                        <input id="files" type="file" name="files[]" multiple class="sr-only" @change="pick($event)"
                            accept="{{ collect(\App\Models\ResearchRequestFile::EXTENSIONS)->map(fn ($e) => '.'.$e)->implode(',') }}">
                    </label>
                    <ul class="mt-2 space-y-1" x-show="files.length" x-cloak>
                        <template x-for="f in files" :key="f.name">
                            <li class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-1.5 text-sm"><span class="truncate" x-text="f.name"></span><span class="ml-3 shrink-0 text-xs text-gray-500" x-text="size(f.size)"></span></li>
                        </template>
                    </ul>
                    <x-input-error :messages="$errors->get('files')" class="mt-1" />
                    @foreach ($errors->get('files.*') as $messages)
                        <x-input-error :messages="$messages" class="mt-1" />
                    @endforeach
                </div>
            </section>

            {{-- 3. Requirements --}}
            <section class="mbui-card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">3</span>
                    <h2 class="text-base font-semibold text-gray-900">Requirements</h2>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="deadline" class="mbui-label">Deadline</label>
                        <input id="deadline" type="date" name="deadline" value="{{ old('deadline') }}" min="{{ now()->toDateString() }}" class="mbui-input mt-1">
                        <x-input-error :messages="$errors->get('deadline')" class="mt-1" />
                    </div>
                    <div>
                        <label for="pages" class="mbui-label">Length (pages)</label>
                        <input id="pages" type="number" name="pages" value="{{ old('pages') }}" min="1" max="2000" class="mbui-input mt-1" placeholder="e.g. 25">
                        <p class="mt-1 text-xs text-gray-400">About 275 words per double-spaced page.</p>
                    </div>
                    <div>
                        <label for="citation_style" class="mbui-label">Citation style</label>
                        <select id="citation_style" name="citation_style" class="mbui-input mt-1">
                            <option value="">Not sure</option>
                            @foreach (\App\Models\ResearchRequest::CITATION_STYLES as $key => $label)
                                <option value="{{ $key }}" @selected(old('citation_style') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="budget" class="mbui-label">Budget <span class="font-normal text-gray-400">(optional)</span></label>
                        <div class="mt-1 flex">
                            <select name="currency" aria-label="Currency" class="mbui-input !w-24 rounded-r-none">
                                @foreach (\App\Models\ResearchRequest::CURRENCIES as $c)
                                    <option value="{{ $c }}" @selected(old('currency', 'TZS') === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                            <input id="budget" type="number" name="budget" value="{{ old('budget') }}" min="0" step="any" class="mbui-input rounded-l-none border-l-0" placeholder="What you plan to spend">
                        </div>
                        <x-input-error :messages="$errors->get('budget')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <span class="mbui-label">Priority</span>
                        <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach (\App\Models\ResearchRequest::PRIORITIES as $key => $label)
                                <label class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium transition"
                                    :class="priority === '{{ $key }}' ? '{{ $key === 'urgent' ? 'border-red-500 bg-red-50 text-red-700' : ($key === 'high' ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-indigo-600 bg-indigo-50 text-indigo-700') }}' : 'border-gray-200 text-gray-700 hover:bg-gray-50'">
                                    <input type="radio" name="priority" value="{{ $key }}" x-model="priority" class="sr-only">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3">
                <x-mbui.btn-link :href="route('user.research-requests.index')" variant="secondary">Cancel</x-mbui.btn-link>
                <x-mbui.button type="submit" ::disabled="sending" class="disabled:opacity-60">
                    <span x-show="! sending">Send request</span>
                    <span x-show="sending" x-cloak>Sending…</span>
                </x-mbui.button>
            </div>
        </div>

        {{-- How it works --}}
        <aside class="space-y-6">
            <div class="mbui-card p-5 lg:sticky lg:top-6">
                <h2 class="text-sm font-semibold text-gray-900">How it works</h2>
                <ol class="relative mt-4 space-y-4 border-l border-gray-200 pl-5" role="list">
                    @foreach ($stages as $stage)
                        <li class="relative">
                            <span class="absolute -left-[27px] top-0.5 h-3.5 w-3.5 rounded-full ring-4 ring-white" style="background-color: {{ $stage->hex() }}"></span>
                            <p class="text-sm font-medium text-gray-900">{{ $stage->name }}</p>
                            @if ($stage->description)<p class="text-xs text-gray-500">{{ $stage->description }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                <div class="mt-5 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800">
                    You can talk with the team on the request page at any time, get drafts to read, and ask for changes before you accept the final work.
                </div>
            </div>
        </aside>
    </form>

</x-layouts.user>
