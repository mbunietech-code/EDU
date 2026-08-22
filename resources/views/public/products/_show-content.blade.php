    <section class="mbui-container py-12">
        <nav class="text-sm text-gray-500">
            <a href="{{ route('public.products.index') }}" class="hover:text-gray-900">AI Tools</a>
            <span class="mx-1">/</span>
            <span class="text-gray-900">{{ $product->name }}</span>
        </nav>

        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex items-start justify-between">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $product->name }}</h1>
                    @if ($product->is_featured)
                        <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                    @endif
                </div>
                <div class="mt-4 prose prose-gray max-w-none">
                    <p class="text-gray-600">{{ $product->description }}</p>
                </div>

                @if ($product->features)
                    <div class="mt-8">
                        <h2 class="mbui-title text-lg">Key features</h2>
                        <ul class="mt-4 space-y-2">
                            @foreach ($product->features as $feature)
                                <li class="flex items-start gap-3 text-sm text-gray-700">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="lg:col-span-1">
                <div class="mbui-card p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-gray-900">Plans</h2>
                    <p class="mt-1 text-sm text-gray-500">Select a plan to continue.</p>
                    <div class="mt-6 space-y-4">
                        @forelse ($product->plans as $plan)
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $plan->name }}</h3>
                                    <span class="text-sm font-bold text-gray-900">TZS {{ number_format($plan->price) }}</span>
                                </div>
                                <div class="mt-1 text-right">
                                    <x-currency-conversion :amount="$plan->price" class="text-xs font-semibold text-gray-600" />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ $plan->description }}</p>
                                <p class="mt-2 text-xs font-medium text-gray-600">{{ $plan->durationLabel() }} duration</p>
                                <a href="{{ route('user.orders.create', ['product' => $product->slug, 'plan' => $plan->id]) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                    Order now
                                </a>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No active plans available for this product.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
