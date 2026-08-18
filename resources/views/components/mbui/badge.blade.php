@props(['appearance' => 'neutral'])

@php
    $classes = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
        'danger' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
        'info' => 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',
        'neutral' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/10',
    ][$appearance];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ' . $classes]) }}>
    {{ $slot }}
</span>