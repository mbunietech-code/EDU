@props(['href' => '#', 'variant' => 'primary'])

@php
    $classes = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500',
        'secondary' => 'bg-white text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-500',
        'ghost' => 'text-gray-700 hover:bg-gray-100',
    ][$variant];
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition ' . $classes]) }}>
    {{ $slot }}
</a>
