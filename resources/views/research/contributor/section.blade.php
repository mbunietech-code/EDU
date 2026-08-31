<x-layouts.app :title="'Edit section — ' . $research->title">

    @php($locked = ! $research->isEditableBy(auth()->user()))

    <nav class="text-sm text-gray-500">
        <a href="{{ route('research.contributor.index') }}" class="hover:text-gray-700">My Research</a>
        <span class="mx-1.5">/</span>
        <a href="{{ route('research.contributor.edit', $research) }}" class="hover:text-gray-700">{{ Str::limit($research->title, 30) }}</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">{{ $chapter->title }}</span>
    </nav>

    <form method="POST" action="{{ route('research.contributor.section.update', [$chapter, $section]) }}"
          x-data="{ tab: 'write', body: @js($section->body ?? '') }" class="mt-4">
        @csrf @method('PUT')

        <x-mbui.card class="p-5">
            <label class="mbui-label">Section heading</label>
            <input name="heading" value="{{ $section->heading }}" @disabled($locked) required maxlength="255"
                   class="mbui-input mt-1" placeholder="e.g. 1.1 Background of the Study">
            <x-input-error :messages="$errors->get('heading')" class="mt-1" />

            <div class="mt-5 flex items-center gap-1 border-b border-gray-200 text-sm">
                <button type="button" @click="tab='write'" :class="tab==='write' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'"
                        class="border-b-2 px-3 py-2 font-medium">Write</button>
                <button type="button" @click="tab='preview'" :class="tab==='preview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'"
                        class="border-b-2 px-3 py-2 font-medium">Preview</button>
            </div>

            <div x-show="tab==='write'" class="mt-3">
                <textarea name="body" x-model="body" @disabled($locked) rows="22" maxlength="200000"
                          class="mbui-input font-mono text-sm leading-6"
                          placeholder="Write in Markdown.&#10;&#10;## A subsection heading&#10;&#10;Your paragraph text. **Bold**, *italic*, and [links](https://...).&#10;&#10;- bullet&#10;- points&#10;&#10;> A quote or key point."></textarea>
                <p class="mt-2 text-xs text-gray-400">
                    Markdown: <code>## Heading</code>, <code>**bold**</code>, <code>*italic*</code>,
                    <code>- list</code>, <code>1. numbered</code>, <code>&gt; quote</code>, <code>[text](url)</code>.
                    Subsection headings (<code>##</code>, <code>###</code>) become navigable in the reader.
                </p>
            </div>

            <div x-show="tab==='preview'" x-cloak class="research-prose mt-3 min-h-[10rem] rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-400" x-show="!body.trim()">Nothing to preview yet.</p>
                <div x-html="renderMarkdown(body)"></div>
            </div>
        </x-mbui.card>

        <div class="mt-4 flex justify-between">
            <x-mbui.btn-link :href="route('research.contributor.edit', $research)" variant="secondary">Back to outline</x-mbui.btn-link>
            @unless ($locked)
                <x-mbui.button type="submit">Save section</x-mbui.button>
            @endunless
        </div>
    </form>

    @push('scripts')
    <script>
        // Minimal Markdown -> HTML for the live preview (server renders the real
        // thing on save with CommonMark). Deliberately small + safe-ish.
        function renderMarkdown(src) {
            if (!src) return '';
            let html = src
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            html = html
                .replace(/^######\s+(.*)$/gm, '<h6>$1</h6>')
                .replace(/^#####\s+(.*)$/gm, '<h5>$1</h5>')
                .replace(/^####\s+(.*)$/gm, '<h4>$1</h4>')
                .replace(/^###\s+(.*)$/gm, '<h3>$1</h3>')
                .replace(/^##\s+(.*)$/gm, '<h2>$1</h2>')
                .replace(/^#\s+(.*)$/gm, '<h1>$1</h1>')
                .replace(/^>\s+(.*)$/gm, '<blockquote>$1</blockquote>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
            // lists
            html = html.replace(/(?:^[-*]\s+.*(?:\n|$))+/gm, m =>
                '<ul>' + m.trim().split(/\n/).map(l => '<li>' + l.replace(/^[-*]\s+/, '') + '</li>').join('') + '</ul>');
            html = html.replace(/(?:^\d+\.\s+.*(?:\n|$))+/gm, m =>
                '<ol>' + m.trim().split(/\n/).map(l => '<li>' + l.replace(/^\d+\.\s+/, '') + '</li>').join('') + '</ol>');
            // paragraphs
            html = html.split(/\n{2,}/).map(block =>
                /^\s*<(h\d|ul|ol|blockquote|pre)/.test(block) ? block : '<p>' + block.replace(/\n/g, '<br>') + '</p>'
            ).join('');
            return html;
        }
    </script>
    @endpush
</x-layouts.app>
