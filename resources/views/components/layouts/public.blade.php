@props(['title' => config('app.name', 'MBUNIETECH'), 'metaDescription' => null, 'bodyClass' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' | ' . config('app.name', 'MBUNIETECH') : config('app.name', 'MBUNIETECH') }}</title>

    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ? $title . ' | ' . config('app.name', 'MBUNIETECH') : config('app.name', 'MBUNIETECH') }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body {{ $attributes->merge(['class' => 'min-h-screen bg-gray-50 ' . $bodyClass]) }}>
    <header class="bg-white/90 backdrop-blur border-b border-gray-200 sticky top-0 z-40">
        <nav class="mbui-container flex h-16 items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('public.home') }}" class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white">M</span>
                    <span class="text-lg font-bold tracking-tight text-gray-900">MBUNIETECH</span>
                </a>

                <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600">
                    <a href="{{ route('public.home') }}" class="hover:text-gray-900">Home</a>
                    <a href="{{ route('public.products.index') }}" class="hover:text-gray-900">AI Tools</a>
                    <a href="{{ route('public.about') }}" class="hover:text-gray-900">About</a>
                    <a href="{{ route('public.faq') }}" class="hover:text-gray-900">FAQ</a>
                    <a href="{{ route('public.contact') }}" class="hover:text-gray-900">Contact</a>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @auth
                    @if (auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Admin Console</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">My Dashboard</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-gray-900">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Get started</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-gray-200 bg-white">
        <div class="mbui-container py-12">
            <div class="grid gap-8 md:grid-cols-4">
                <div class="md:col-span-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white">M</span>
                        <span class="text-base font-bold tracking-tight text-gray-900">MBUNIETECH</span>
                    </div>
                    <p class="mt-3 max-w-md text-sm text-gray-500">
                        Authorized AI access management for individuals and businesses. Simple payments, managed access, professional support.
                    </p>
                </div>
                <div>
                    <h4 class="mbui-section-label">Explore</h4>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li><a class="hover:text-gray-900" href="{{ route('public.products.index') }}">AI Tools</a></li>
                        <li><a class="hover:text-gray-900" href="{{ route('public.about') }}">About</a></li>
                        <li><a class="hover:text-gray-900" href="{{ route('public.faq') }}">FAQ</a></li>
                        <li><a class="hover:text-gray-900" href="{{ route('public.contact') }}">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="mbui-section-label">Account</h4>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        @auth
                            <li><a class="hover:text-gray-900" href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li><a class="hover:text-gray-900" href="{{ route('user.subscriptions.index') }}">Subscriptions</a></li>
                        @else
                            <li><a class="hover:text-gray-900" href="{{ route('login') }}">Sign in</a></li>
                            <li><a class="hover:text-gray-900" href="{{ route('register') }}">Register</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
            <div class="mt-10 border-t border-gray-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} MBUNIETECH. All rights reserved.</p>
                <p class="text-xs text-gray-400">mt.co.tz</p>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>