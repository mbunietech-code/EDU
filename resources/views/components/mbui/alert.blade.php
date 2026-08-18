@props(['type' => 'success'])

@php
    $styles = [
        'success' => ['bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'svg' => 'M12 9v4m0 4h.01', 'path2' => true],
        'error' => ['bg-red-50', 'text-red-800', 'border-red-200', 'svg' => 'M12 9v4m0 4h.01', 'path2' => true],
        'warning' => ['bg-amber-50', 'text-amber-800', 'border-amber-200', 'svg' => 'M12 9v4m0 4h.01', 'path2' => true],
        'info' => ['bg-sky-50', 'text-sky-800', 'border-sky-200', 'svg' => 'M13 16h-1v-4h-1m1-4h.01', 'path2' => true],
    ][$type];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm ' . implode(' ', $styles)]) }}>
    {{ $slot }}
</div>