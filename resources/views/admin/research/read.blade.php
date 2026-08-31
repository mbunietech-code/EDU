<x-layouts.admin :title="$chapter->title . ' — ' . $research->title" header="Chapter preview">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('admin.research.index') }}" class="hover:text-gray-700">Research</a>
        <span class="mx-1.5">/</span>
        <a href="{{ route('admin.research.show', $research) }}" class="hover:text-gray-700">{{ Str::limit($research->title, 30) }}</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">{{ $chapter->title }}</span>
    </nav>

    <div class="mt-4 lg:grid lg:grid-cols-[15rem_1fr] lg:gap-8">
        <aside class="mb-6 lg:mb-0">
            <div class="lg:sticky lg:top-6">
                <h2 class="mbui-section-label">Chapters</h2>
                <nav class="mt-3 space-y-1 text-sm">
                    @foreach ($research->chapters as $ch)
                        <a href="{{ route('admin.research.read', [$research, $ch]) }}"
                           class="block rounded px-2 py-1 {{ $ch->id === $chapter->id ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            {{ $ch->title }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

        <article class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $chapter->title }}</h1>
            @foreach ($chapter->sections as $section)
                <section class="mt-8 border-t border-gray-100 pt-6 first:mt-6 first:border-0 first:pt-0">
                    <h2 class="text-lg font-bold text-gray-900">{{ $section->heading }}</h2>
                    <div class="research-prose mt-3">{!! $section->bodyHtml() !!}</div>
                </section>
            @endforeach
            @if ($chapter->sections->isEmpty())
                <p class="mt-8 text-sm text-gray-400">This chapter has no sections.</p>
            @endif
        </article>
    </div>
</x-layouts.admin>
