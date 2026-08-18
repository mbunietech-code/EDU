<x-layouts.public title="AI Access Management" metaDescription="MbunieEduHub - Authorized AI tools and access management for Tanzania. Simple payments, managed subscriptions, professional support.">

    <section class="relative overflow-hidden bg-gray-900">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/20 via-gray-900 to-gray-900"></div>
        <div class="relative mbui-container py-20 sm:py-28">
            <div class="max-w-3xl">
                <p class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-indigo-300 ring-1 ring-inset ring-white/20">
                    Authorized AI Access Management
                </p>
                <h1 class="mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Premium AI tools,<br>
                    <span class="text-indigo-400">managed for you.</span>
                </h1>
                <p class="mt-6 max-w-2xl text-lg text-gray-300">
                    MbunieEduHub gives you authorized access to leading AI tools with transparent pricing, simple manual payments, and subscriptions that just work.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('public.products.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Explore AI Tools
                    </a>
                    <a href="{{ route('public.about') }}" class="inline-flex items-center justify-center rounded-lg bg-white/10 px-6 py-3 text-sm font-semibold text-white ring-1 ring-inset ring-white/20 hover:bg-white/20">
                        Learn more
                    </a>
                </div>
            </div>
        </div>
    </section>

    @if ($featuredProducts->isNotEmpty())
        <section class="mbui-container py-16">
            <div class="flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Popular AI Tools</h2>
                    <p class="mt-1 text-sm text-gray-500">Trusted by users across Tanzania</p>
                </div>
                <a href="{{ route('public.products.index') }}" class="mbui-anchor text-sm">View all</a>
            </div>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredProducts as $product)
                    <a href="{{ route('public.products.show', $product) }}" class="mbui-card group p-6 transition hover:shadow-md">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-40 w-full rounded-lg object-cover">
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            </div>
                        @endif
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">{{ $product->name }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm text-gray-500">{{ $product->description }}</p>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-lg font-bold text-gray-900">TZS {{ number_format($product->price) }}</span>
                            <span class="mbui-anchor text-sm">View plans &rarr;</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">
                            &asymp; ${{ number_format($product->price * $rates['USD'], 2) }} USD &middot; &asymp; &yen;{{ number_format($product->price * $rates['CNY'], 2) }} CNY
                        </p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="border-y border-gray-200 bg-white">
        <div class="mbui-container py-16">
            <h2 class="text-center text-2xl font-bold tracking-tight text-gray-900">How it works</h2>
            <div class="mt-10 grid gap-8 md:grid-cols-4">
                @foreach ([
                    ['Browse', 'Explore our catalogue of authorized AI tools.'],
                    ['Order & Pay', 'Select a plan and pay via your preferred method.'],
                    ['Get Access', 'We verify your payment and activate your subscription.'],
                    ['Enjoy & Manage', 'Track your access, expiry and renew when needed.'],
                ] as [$step, $desc])
                    <div class="text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">{{ $loop->iteration }}</div>
                        <h3 class="mt-3 text-base font-semibold text-gray-900">{{ $step }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mbui-container py-16">
        <div class="rounded-2xl bg-gray-900 p-8 sm:p-12 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">Ready to get started?</h2>
            <p class="mx-auto mt-3 max-w-xl text-gray-300">Choose an AI tool, subscribe, and get secure managed access today.</p>
            <div class="mt-6 flex justify-center">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Create your account
                </a>
            </div>
        </div>
    </section>

</x-layouts.public>