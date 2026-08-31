<x-layouts.public title="Research & Consultancy"
    metaDescription="Browse research work and consultancy guides published on MbunieEduHub. Sign in to read the full papers.">

    <section class="mbui-container py-12">
        <div class="mbui-page-header">
            <div>
                <h1 class="mbui-title">Research &amp; Consultancy</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Research work and consultancy guides written by our contributors and reviewed before publishing.
                    <span class="font-medium text-gray-700">Sign in to read a paper in full.</span>
                </p>
            </div>
            @auth
                @if (auth()->user()->canWriteResearch())
                    <a href="{{ route('research.contributor.create') }}"
                       class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Write research
                    </a>
                @endif
            @endauth
        </div>

        @if ($categories->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <a href="{{ route('research.category', $category) }}"
                       class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-indigo-200 hover:text-indigo-600">
                        {{ $category->name }}
                        <span class="text-xs text-gray-400">{{ $category->published_count }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-8">
            @if ($researches->isEmpty())
                <x-mbui.empty-state title="Nothing published yet" message="Approved research will appear here." />
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($researches as $research)
                        <a href="{{ route('research.show', $research) }}" class="mbui-card group flex flex-col p-5 transition hover:shadow-md">
                            @if ($research->category)
                                <span class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ $research->category->name }}</span>
                            @endif
                            <h3 class="mt-1 text-base font-semibold text-gray-900 group-hover:text-indigo-600">{{ $research->title }}</h3>
                            @if ($research->summary)
                                <p class="mt-2 line-clamp-3 flex-1 text-sm text-gray-500">{{ $research->summary }}</p>
                            @endif
                            <div class="mt-4 flex items-center justify-between text-xs text-gray-400">
                                <span>{{ $research->author->name ?? 'Contributor' }}</span>
                                <span>{{ $research->chapters_count }} {{ Str::plural('chapter', $research->chapters_count) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-8">{{ $researches->links() }}</div>
            @endif
        </div>
    </section>
</x-layouts.public>
