@props(['title' => config('app.name', 'MbunieEduHub')])

{{-- Full-viewport shell for the live classroom: no sidebar, no page chrome —
     the page supplies its own top bar, stage, side panel and control bar. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>[x-cloak]{display:none!important;}</style>
    <title>{{ $title ? $title . ' | ' . config('app.name', 'MbunieEduHub') : config('app.name', 'MbunieEduHub') }}</title>
    <link rel="icon" href="{{ \App\Support\Branding::faviconUrl() ?? asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full overflow-hidden bg-gray-950 text-gray-100">
    <div {{ $attributes->merge(['class' => 'flex h-screen flex-col']) }}>
        {{ $slot }}
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
