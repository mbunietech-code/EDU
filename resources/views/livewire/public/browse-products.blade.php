<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search AI tools..."
                class="mbui-input pl-10 w-full sm:w-80">
            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
        </div>
        <div>
            <select wire:model.live="sort" class="mbui-input sm:w-44">
                <option value="latest">Sort: Newest</option>
                <option value="featured">Featured</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>
    </div>

    @if ($products->isEmpty())
        <x-mbui.empty-state title="No AI tools found" message="Try adjusting your search or filters." />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <a href="{{ route('public.products.show', $product) }}" class="mbui-card group flex items-center gap-3 p-3 transition hover:shadow-md">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-contain p-1.5">
                        @else
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="truncate text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $product->name }}</h3>
                            @if ($product->is_featured)
                                <x-mbui.badge appearance="warning" class="shrink-0">Featured</x-mbui.badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm font-bold text-gray-900">TZS {{ number_format($product->price) }}</p>
                        <p class="text-xs font-semibold text-gray-600">
                            &asymp; ${{ number_format($product->price * $rates['USD'], 2) }} USD &middot; &asymp; &yen;{{ number_format($product->price * $rates['CNY'], 2) }} CNY
                        </p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $products->links() }}
        </div>
    @endif
</div>