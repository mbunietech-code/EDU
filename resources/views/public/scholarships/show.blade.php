<x-layouts.public :title="$scholarship->title" :meta-description="$scholarship->description">

    <section class="mbui-container py-12">
        <nav class="text-sm text-gray-500">
            <a href="{{ route('public.scholarships.index') }}" class="hover:text-gray-900">Scholarships</a>
            <span class="mx-1">/</span>
            <span class="text-gray-900">{{ $scholarship->title }}</span>
        </nav>

        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex items-start justify-between gap-3">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $scholarship->title }}</h1>
                    @if ($scholarship->is_featured)
                        <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                    @endif
                </div>
                @if ($scholarship->country)
                    <p class="mt-1 text-sm text-gray-500">{{ $scholarship->country }}</p>
                @endif
                @if ($scholarship->description)
                    <div class="mt-4 prose prose-gray max-w-none">
                        <p class="whitespace-pre-line text-gray-600">{{ $scholarship->description }}</p>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-1">
                <div class="mbui-card p-6 sticky top-24">
                    @if ($scholarship->deadline)
                        <p class="mbui-section-label">Application deadline</p>
                        <p class="mt-1 text-lg font-bold {{ $scholarship->isExpired() ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $scholarship->deadline->format('d M Y') }}
                        </p>
                        @if ($scholarship->isExpired())
                            <p class="mt-1 text-xs text-red-600">This deadline has passed.</p>
                        @endif
                    @endif
                    @if ($scholarship->apply_url)
                        <a href="{{ $scholarship->apply_url }}" target="_blank" rel="noopener" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            Apply now
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
