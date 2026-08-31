<x-layouts.app :title="$research->title" :header="$research->title">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('library.index') }}" class="hover:text-gray-700">Research &amp; Consultancy</a>
        @if ($research->category)
            <span class="mx-1.5">/</span>
            <a href="{{ route('library.category', $research->category) }}" class="hover:text-gray-700">{{ $research->category->name }}</a>
        @endif
    </nav>

    <div class="mt-4 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $research->title }}</h1>
            <p class="mt-1 text-sm text-gray-500">by {{ $research->author->name ?? 'Contributor' }} · published {{ $research->published_at?->format('d M Y') }} · {{ $research->views }} {{ Str::plural('view', $research->views) }}</p>

            @if ($research->summary)
                <p class="mt-4 text-sm leading-relaxed text-gray-700">{{ $research->summary }}</p>
            @endif

            @if ($progress && $progress->percent > 0)
                <div class="mt-5">
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>Your progress</span><span>{{ $progress->percent }}%</span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ $progress->percent }}%"></div>
                    </div>
                </div>
            @endif

            @php($startChapter = $research->chapters->first())
            @if ($startChapter)
                <div class="mt-6">
                    <x-mbui.btn-link :href="route('library.read', [$research, $startChapter])">
                        {{ $progress && $progress->percent > 0 ? 'Continue reading' : 'Start reading' }}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </x-mbui.btn-link>
                </div>
            @endif
        </div>

        <div>
            <x-mbui.card class="p-5">
                <h2 class="mbui-section-label">Contents</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ($research->chapters as $chapter)
                        <li>
                            <a href="{{ route('library.read', [$research, $chapter]) }}" class="text-sm font-semibold text-gray-900 hover:text-indigo-600">
                                {{ $chapter->title }}
                            </a>
                            @if ($chapter->sections->isNotEmpty())
                                <ul class="mt-1 space-y-0.5 border-l border-gray-200 pl-3 text-xs text-gray-500">
                                    @foreach ($chapter->sections as $section)
                                        <li>{{ $section->heading }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-mbui.card>
        </div>
    </div>
</x-layouts.app>
