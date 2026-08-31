<x-layouts.public :title="$research->title" :metaDescription="$research->summary">

    <section class="mbui-container py-12">
        <nav class="text-sm text-gray-500">
            <a href="{{ route('research.index') }}" class="hover:text-gray-700">Research &amp; Consultancy</a>
            @if ($research->category)
                <span class="mx-1.5">/</span>
                <a href="{{ route('research.category', $research->category) }}" class="hover:text-gray-700">{{ $research->category->name }}</a>
            @endif
        </nav>

        <div class="mt-4 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($research->category)
                    <span class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ $research->category->name }}</span>
                @endif
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $research->title }}</h1>
                <p class="mt-2 text-sm text-gray-500">
                    by {{ $research->author->name ?? 'Contributor' }} · published {{ $research->published_at?->format('d M Y') }}
                </p>

                @if ($research->summary)
                    <p class="mt-5 text-base leading-relaxed text-gray-700">{{ $research->summary }}</p>
                @endif

                <div class="mt-6 rounded-xl border border-indigo-100 bg-indigo-50/50 p-5">
                    <p class="text-sm font-semibold text-gray-900">Read the full research</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Sign in to open every chapter, follow the outline, and track your reading progress.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($firstChapter)
                            <a href="{{ route('library.read', [$research, $firstChapter]) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Sign in to read
                            </a>
                        @endif
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Create an account
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <div class="mbui-card p-5">
                    <h2 class="mbui-section-label">Outline</h2>
                    <ol class="mt-3 space-y-3">
                        @foreach ($research->chapters as $chapter)
                            <li>
                                <p class="text-sm font-semibold text-gray-900">{{ $chapter->title }}</p>
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
                    <p class="mt-4 flex items-center gap-1.5 text-xs text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                        Full text available after sign-in
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
