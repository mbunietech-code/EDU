<x-layouts.public :title="$category->name">

    <section class="mbui-container py-12">
        <nav class="text-sm text-gray-500">
            <a href="{{ route('research.index') }}" class="hover:text-gray-700">Research &amp; Consultancy</a>
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

        <div class="mt-8">
            @if ($researches->isEmpty())
                <x-mbui.empty-state title="Nothing here yet" />
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($researches as $research)
                        <a href="{{ route('research.show', $research) }}" class="mbui-card group flex flex-col p-5 transition hover:shadow-md">
                            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">{{ $research->title }}</h3>
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
