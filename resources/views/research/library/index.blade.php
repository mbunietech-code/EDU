<x-layouts.app title="Research & Consultancy" header="Research & Consultancy">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Research &amp; Consultancy</h1>
            <p class="mt-1 text-sm text-gray-500">Research work and consultancy guides, reviewed before publishing.</p>
        </div>
        <div class="flex gap-2">
            @if (auth()->user()->canWriteResearch())
                <x-mbui.btn-link :href="route('research.contributor.index')" variant="secondary">My Research</x-mbui.btn-link>
                <x-mbui.btn-link :href="route('research.contributor.create')">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Write research
                </x-mbui.btn-link>
            @endif
        </div>
    </div>

    @if ($continue->isNotEmpty())
        <div class="mt-8">
            <h2 class="mbui-section-label">Continue reading</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach ($continue as $p)
                    <a href="{{ route('library.show', $p->research) }}" class="mbui-card p-4 transition hover:shadow-md">
                        <p class="text-xs font-medium text-indigo-600">{{ $p->research->category->name ?? 'Research' }}</p>
                        <p class="mt-1 line-clamp-2 text-sm font-semibold text-gray-900">{{ $p->research->title }}</p>
                        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-indigo-600" style="width: {{ $p->percent }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">{{ $p->percent }}% complete</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-8">
        <h2 class="mbui-section-label">Browse by area</h2>
        @if ($categories->where('published_count', '>', 0)->isEmpty())
            <x-mbui.card class="mt-3">
                <x-mbui.empty-state title="Nothing published yet" message="Research appears here once it has been reviewed and approved." />
            </x-mbui.card>
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    @continue($category->published_count === 0)
                    <a href="{{ route('library.category', $category) }}" class="mbui-card group flex items-start gap-3 p-4 transition hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $category->name }}</h3>
                            @if ($category->description)
                                <p class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ $category->description }}</p>
                            @endif
                            <p class="mt-1 text-xs font-medium text-gray-400">{{ $category->published_count }} {{ Str::plural('paper', $category->published_count) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if ($recent->isNotEmpty())
        <div class="mt-10">
            <h2 class="mbui-section-label">Recently published</h2>
            <div class="mt-3 space-y-2">
                @foreach ($recent as $research)
                    <a href="{{ route('library.show', $research) }}" class="mbui-card flex items-center justify-between gap-4 p-4 transition hover:shadow-md">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-indigo-600">{{ $research->category->name ?? 'Research' }}</p>
                            <h3 class="truncate text-sm font-semibold text-gray-900">{{ $research->title }}</h3>
                            <p class="text-xs text-gray-400">by {{ $research->author->name ?? 'Contributor' }} · {{ $research->published_at?->diffForHumans() }}</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.app>
