<x-layouts.app :title="$research->title" :header="'Edit — ' . $research->title">

    @php($editable = $research->isEditableBy(auth()->user()))
    @php($locked = ! $editable)

    <nav class="text-sm text-gray-500">
        <a href="{{ route('research.contributor.index') }}" class="hover:text-gray-700">My Research</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">{{ Str::limit($research->title, 40) }}</span>
    </nav>

    <div class="mbui-page-header mt-3">
        <div class="flex items-center gap-3">
            <h1 class="mbui-title">{{ $research->title }}</h1>
            <x-mbui.status-badge :status="$research->status" />
        </div>
        <div class="flex items-center gap-2">
            @if ($research->isPublished())
                <x-mbui.btn-link :href="route('library.show', $research)" variant="secondary">View live</x-mbui.btn-link>
            @endif
            @if ($research->canBeSubmitted())
                <form method="POST" action="{{ route('research.contributor.submit', $research) }}"
                      onsubmit="return confirm('Submit this research for review? You will not be able to edit it while it is under review.')">
                    @csrf
                    <x-mbui.button type="submit" variant="success">Submit for review</x-mbui.button>
                </form>
            @endif
        </div>
    </div>

    @if ($research->isUnderReview())
        <x-mbui.alert type="info" class="mt-4">
            This research is <strong>{{ $research->statusLabel() }}</strong>. Content is locked until the review is done.
        </x-mbui.alert>
    @elseif ($research->isPublished() && $editable)
        <x-mbui.alert type="warning" class="mt-4">
            This research is <strong>published</strong> — any change you save here goes live to readers immediately.
        </x-mbui.alert>
    @endif

    @if ($research->status === 'changes_requested' && $research->review_note)
        <x-mbui.alert type="warning" class="mt-4">
            <p class="font-semibold">The reviewer asked for changes:</p>
            <p class="mt-1 whitespace-pre-line">{{ $research->review_note }}</p>
        </x-mbui.alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Chapters + sections --}}
        <div class="lg:col-span-2 space-y-4">

            @unless ($locked)
                <x-mbui.card class="p-5" x-data="{ open: {{ $research->chapters->isEmpty() ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
                        <span>
                            <span class="mbui-section-label">Import from a document</span>
                            <span class="mt-0.5 block text-xs text-gray-500">Upload a Word / Markdown / text file, or paste your text. Chapters and sections are created from the headings.</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>

                    <form x-show="open" x-cloak method="POST" action="{{ route('research.contributor.import', $research) }}"
                          enctype="multipart/form-data" class="mt-4 space-y-3"
                          onsubmit="return confirm('Import will ADD new chapters and sections to this research (it does not replace what is already here). Continue?')">
                        @csrf
                        <div>
                            <label class="mbui-label">Document <span class="text-gray-400">(.docx, .md, .txt — up to 15&nbsp;MB)</span></label>
                            <input type="file" name="document" accept=".docx,.md,.markdown,.txt"
                                   class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>
                        <p class="text-center text-xs text-gray-400">— or —</p>
                        <div>
                            <label class="mbui-label">Paste text</label>
                            <textarea name="text" rows="6" class="mbui-input mt-1 font-mono text-xs"
                                      placeholder="# Chapter One: Introduction&#10;&#10;## 1.1 Background of the Study&#10;&#10;Your text here…&#10;&#10;## 1.2 Statement of the Problem&#10;&#10;More text…"></textarea>
                        </div>
                        <p class="text-xs text-gray-400">
                            In Word, use the <strong>Heading&nbsp;1</strong> style for chapter titles and <strong>Heading&nbsp;2/3</strong> for sections.
                            In text, start a line with <code>#</code> for a chapter and <code>##</code> for a section.
                        </p>
                        <x-mbui.button type="submit">Import into this research</x-mbui.button>
                    </form>
                </x-mbui.card>
            @endunless

            @foreach ($research->chapters as $chapter)
                <x-mbui.card class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <form method="POST" action="{{ route('research.contributor.chapter.update', $chapter) }}" class="flex flex-1 items-center gap-2">
                            @csrf @method('PUT')
                            <input name="title" value="{{ $chapter->title }}" @disabled($locked)
                                   class="mbui-input flex-1 font-semibold" required maxlength="255">
                            @unless ($locked)
                                <button class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Rename</button>
                            @endunless
                        </form>
                        @unless ($locked)
                            <form method="POST" action="{{ route('research.contributor.chapter.destroy', $chapter) }}"
                                  onsubmit="return confirm('Delete this chapter and all its sections?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                            </form>
                        @endunless
                    </div>

                    <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100">
                        @forelse ($chapter->sections as $section)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <a href="{{ route('research.contributor.section.edit', [$chapter, $section]) }}"
                                   class="min-w-0 flex-1 text-sm text-gray-800 hover:text-indigo-600">
                                    <span class="font-medium">{{ $section->heading }}</span>
                                    <span class="block truncate text-xs text-gray-400">{{ $section->excerpt(16) ?: 'No content yet' }}</span>
                                </a>
                                @unless ($locked)
                                    <form method="POST" action="{{ route('research.contributor.section.destroy', [$chapter, $section]) }}"
                                          onsubmit="return confirm('Delete this section?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                @endunless
                            </li>
                        @empty
                            <li class="py-2.5 text-xs text-gray-400">No sections yet.</li>
                        @endforelse
                    </ul>

                    @unless ($locked)
                        <form method="POST" action="{{ route('research.contributor.section.create', $chapter) }}" class="mt-3">
                            @csrf
                            <button class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Add section</button>
                        </form>
                    @endunless
                </x-mbui.card>
            @endforeach

            @unless ($locked)
                <x-mbui.card class="p-5">
                    <form method="POST" action="{{ route('research.contributor.chapter.store', $research) }}" class="flex items-end gap-2">
                        @csrf
                        <div class="flex-1">
                            <label class="mbui-label">New chapter</label>
                            <input name="title" required maxlength="255" class="mbui-input mt-1" placeholder="e.g. Chapter One: Introduction">
                        </div>
                        <x-mbui.button type="submit">Add chapter</x-mbui.button>
                    </form>
                </x-mbui.card>
            @endunless
        </div>

        {{-- Details + history --}}
        <div class="space-y-4">
            <x-mbui.card class="p-5">
                <h2 class="mbui-section-label">Details</h2>
                <form method="POST" action="{{ route('research.contributor.update', $research) }}" class="mt-3 space-y-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="mbui-label">Title</label>
                        <input name="title" value="{{ $research->title }}" @disabled($locked) required class="mbui-input mt-1">
                    </div>
                    <div>
                        <label class="mbui-label">Area</label>
                        <select name="research_category_id" @disabled($locked) class="mbui-input mt-1">
                            <option value="">— none —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($research->research_category_id == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mbui-label">Summary</label>
                        <textarea name="summary" rows="3" @disabled($locked) class="mbui-input mt-1">{{ $research->summary }}</textarea>
                    </div>
                    @unless ($locked)
                        <x-mbui.button type="submit" variant="secondary" class="w-full">Save details</x-mbui.button>
                    @endunless
                </form>
            </x-mbui.card>

            @if ($research->reviews->isNotEmpty())
                <x-mbui.card class="p-5">
                    <h2 class="mbui-section-label">Review history</h2>
                    <ol class="mt-3 space-y-3">
                        @foreach ($research->reviews as $review)
                            <li class="text-xs">
                                <p class="font-semibold text-gray-800">{{ $review->actionLabel() }}</p>
                                <p class="text-gray-400">{{ $review->reviewer->name ?? 'You' }} · {{ $review->created_at->diffForHumans() }}</p>
                                @if ($review->comment)<p class="mt-1 text-gray-600">{{ $review->comment }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </x-mbui.card>
            @endif

            @if (in_array($research->status, ['draft', 'changes_requested', 'archived']))
                <form method="POST" action="{{ route('research.contributor.destroy', $research) }}"
                      onsubmit="return confirm('Delete this research permanently?')">
                    @csrf @method('DELETE')
                    <button class="text-xs font-medium text-red-600 hover:text-red-800">Delete this research</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
