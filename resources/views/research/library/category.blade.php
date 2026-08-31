<x-layouts.app :title="$category->name" :header="$category->name">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('library.index') }}" class="hover:text-gray-700">Research &amp; Consultancy</a>
        <span class="mx-1.5">/</span><span class="text-gray-700">{{ $category->name }}</span>
    </nav>

    <div class="mbui-page-header mt-3">
        <div>
            <h1 class="mbui-title">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-1 text-sm text-gray-500">{{ $category->description }}</p>
            @endif
        </div>
    </div>

    <div class="mt-6 space-y-2">
        @forelse ($researches as $research)
            <a href="{{ route('library.show', $research) }}" class="mbui-card block p-5 transition hover:shadow-md">
                <h3 class="text-base font-semibold text-gray-900">{{ $research->title }}</h3>
                @if ($research->summary)
                    <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $research->summary }}</p>
                @endif
                <p class="mt-2 text-xs text-gray-400">by {{ $research->author->name ?? 'Contributor' }} · {{ $research->published_at?->diffForHumans() }}</p>
            </a>
        @empty
            <x-mbui.card><x-mbui.empty-state title="Nothing here yet" /></x-mbui.card>
        @endforelse
    </div>

    <div class="mt-6">{{ $researches->links() }}</div>
</x-layouts.app>
