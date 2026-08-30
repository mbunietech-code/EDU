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
                    MbunieEduHub gives you authorized access to leading AI tools with transparent pricing and subscriptions that just work.
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

    <x-public.ticker :items="$tickerItems ?? []" />

    @if ($scholarships->isNotEmpty())
        <section class="border-y border-gray-200 bg-white">
            <div class="mbui-container py-16">
                <div class="flex items-end justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Scholarships</h2>
                        <p class="mt-1 text-sm text-gray-500">Open opportunities you can apply to right now</p>
                    </div>
                    <a href="{{ route('public.scholarships.index') }}" class="mbui-anchor text-sm">View all</a>
                </div>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
                            <h3 class="mt-4 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">{{ $scholarship->title }}</h3>
                            @if ($scholarship->country)
                                <p class="mt-1 text-sm text-gray-500">{{ $scholarship->country }}</p>
                            @endif
                            <div class="mt-4 flex items-center justify-between">
                                @if ($scholarship->deadline)
                                    <span class="text-sm font-medium text-gray-900">Deadline: {{ $scholarship->deadline->format('d M Y') }}</span>
                                @else
                                    <span></span>
                                @endif
                                <span class="mbui-anchor text-sm">Details &rarr;</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

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