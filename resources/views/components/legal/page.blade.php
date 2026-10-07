@props([
    'title',
    'intro' => null,
    'updated',
    'readingTime' => null,
    'sections' => [],   // [anchor id => heading] for the table of contents
])

@php
    $legalLinks = [
        'public.terms' => 'Terms of Service',
        'public.privacy' => 'Privacy Policy',
        'public.account-deletion' => 'Delete your account',
    ];
@endphp

<x-layouts.public :title="$title" :metaDescription="$intro ?? $title.' for MbunieEduHub.'">

    <div x-data="{
            active: @js(array_key_first($sections)),
            tocOpen: false,
            showTop: false,
            init() {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((e) => { if (e.isIntersecting) this.active = e.target.id; });
                }, { rootMargin: '-20% 0px -70% 0px' });
                this.$root.querySelectorAll('article section[id]').forEach((s) => io.observe(s));
            },
         }"
         @scroll.window.throttle.150ms="showTop = window.scrollY > 600">

        {{-- Header band --}}
        <section class="relative overflow-hidden bg-[#21327F] text-white print:bg-white print:text-gray-900">
            <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-sky-400/20 blur-3xl print:hidden" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-indigo-400/20 blur-3xl print:hidden" aria-hidden="true"></div>
            <div class="mbui-container relative py-12 sm:py-16">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-300 print:text-gray-500">Legal</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ $title }}</h1>
                @if ($intro)
                    <p class="mt-4 max-w-3xl text-base leading-relaxed text-indigo-100 print:text-gray-600">{{ $intro }}</p>
                @endif
                <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-indigo-100 print:text-gray-600">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        Last updated {{ $updated }}
                    </span>
                    @if ($readingTime)
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            {{ $readingTime }} min read
                        </span>
                    @endif
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 hover:text-white print:hidden">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                        Print / save as PDF
                    </button>
                </div>
                <nav class="mt-6 flex flex-wrap gap-2 print:hidden" aria-label="Legal pages">
                    @foreach ($legalLinks as $route => $label)
                        <a href="{{ route($route) }}"
                           @if (request()->routeIs($route)) aria-current="page" @endif
                           @class([
                               'rounded-full px-3.5 py-1.5 text-xs font-semibold transition',
                               'bg-white text-[#21327F]' => request()->routeIs($route),
                               'bg-white/10 text-white ring-1 ring-inset ring-white/25 hover:bg-white/20' => ! request()->routeIs($route),
                           ])>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </section>

        <section class="mbui-container py-8 sm:py-12">
            <div class="grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-12">

                {{-- Table of contents: sticky on desktop, collapsible on phones --}}
                @if ($sections)
                    <aside class="print:hidden lg:sticky lg:top-24 lg:self-start">
                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                            <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left lg:pointer-events-none"
                                    @click="tocOpen = !tocOpen" :aria-expanded="tocOpen.toString()">
                                <span class="text-xs font-semibold uppercase tracking-widest text-gray-500">On this page</span>
                                <svg class="h-4 w-4 text-gray-400 transition lg:hidden" :class="tocOpen && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            </button>
                            <ol class="max-h-[70vh] space-y-0.5 overflow-y-auto border-t border-gray-100 p-2 text-sm lg:block" :class="tocOpen ? 'block' : 'hidden'">
                                @foreach ($sections as $id => $heading)
                                    <li>
                                        <a href="#{{ $id }}" @click="tocOpen = false"
                                           class="flex gap-2 rounded-lg border-l-2 px-2.5 py-1.5 transition"
                                           :class="active === @js($id) ? 'border-indigo-600 bg-indigo-50 font-medium text-indigo-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900'">
                                            <span class="w-5 shrink-0 text-right text-xs leading-5 text-gray-400">{{ $loop->iteration }}</span>
                                            <span>{{ $heading }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </aside>
                @endif

                {{-- Body --}}
                <article class="min-w-0 max-w-3xl space-y-12 text-[15px] leading-7 text-gray-700">
                    {{ $slot }}

                    <div class="flex flex-col gap-4 rounded-2xl bg-[#21327F] p-6 text-white sm:flex-row sm:items-center sm:justify-between print:hidden">
                        <div>
                            <h2 class="text-base font-semibold">Still have a question?</h2>
                            <p class="mt-1 text-sm text-indigo-100">Write to us, or use the support chat in the MHub app.</p>
                        </div>
                        <a href="{{ route('public.contact') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[#21327F] hover:bg-indigo-50">Contact us</a>
                    </div>
                </article>
            </div>
        </section>

        {{-- Back to top --}}
        <button type="button" x-show="showTop" x-transition.opacity x-cloak
                @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                class="fixed bottom-6 right-6 z-30 inline-flex h-11 w-11 items-center justify-center rounded-full bg-[#21327F] text-white shadow-lg hover:bg-indigo-800 print:hidden"
                aria-label="Back to top">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
        </button>
    </div>

</x-layouts.public>
