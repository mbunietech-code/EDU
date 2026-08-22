<x-layouts.public title="Scholarships" metaDescription="Browse open scholarship opportunities and apply directly.">

    <section class="mbui-container py-12">
        <div class="mbui-page-header">
            <div>
                <h1 class="mbui-title">Scholarships</h1>
                <p class="mt-1 text-sm text-gray-500">Open scholarship opportunities. Apply directly with the institution.</p>
            </div>
        </div>

        <div class="mt-8">
            @if ($scholarships->isEmpty())
                <x-mbui.empty-state title="No scholarships listed yet" message="Check back soon for new opportunities." />
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($scholarships as $scholarship)
                        <a href="{{ route('public.scholarships.show', $scholarship) }}" class="mbui-card group p-6 transition hover:shadow-md">
                            @if ($scholarship->imageUrl())
                                <img src="{{ $scholarship->imageUrl() }}" alt="{{ $scholarship->title }}" class="h-28 w-full rounded-lg border border-gray-100 bg-gray-50 object-contain p-3">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347M12 3.493a59.902 59.902 0 0110.399 5.84A50.697 50.697 0 0112 13.489a50.702 50.702 0 01-10.399-4.156A59.905 59.905 0 0112 3.493z" />
                                    </svg>
                                </div>
                            @endif
                            <div class="mt-4 flex items-start justify-between gap-2">
                                <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600">{{ $scholarship->title }}</h3>
                                @if ($scholarship->is_featured)
                                    <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                                @endif
                            </div>
                            @if ($scholarship->country)
                                <p class="mt-1 text-sm text-gray-500">{{ $scholarship->country }}</p>
                            @endif
                            @if ($scholarship->description)
                                <p class="mt-2 line-clamp-2 text-sm text-gray-500">{{ $scholarship->description }}</p>
                            @endif
                            <div class="mt-4 flex items-center justify-between">
                                @if ($scholarship->deadline)
                                    <span class="text-sm font-medium {{ $scholarship->isExpired() ? 'text-red-600' : 'text-gray-900' }}">
                                        Deadline: {{ $scholarship->deadline->format('d M Y') }}
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                <span class="mbui-anchor text-sm">Details &rarr;</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-8">{{ $scholarships->links() }}</div>
            @endif
        </div>
    </section>

</x-layouts.public>
