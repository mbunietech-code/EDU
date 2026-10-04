@props(['order', 'href', 'showDate' => true, 'dateFormat' => 'd M Y'])

@php
    $tone = $order->displayStatus();

    $linkClass = match ($tone) {
        'active' => 'text-emerald-700 hover:text-emerald-800',
        'expired', 'rejected', 'cancelled' => 'text-red-600 hover:text-red-700',
        'pending' => 'text-sky-700 hover:text-sky-800',
        default => 'text-indigo-600 hover:text-indigo-800',
    };

    $dateClass = match ($tone) {
        'active' => 'text-emerald-600',
        'expired', 'rejected', 'cancelled' => 'text-red-500',
        'pending' => 'text-sky-600',
        default => 'text-gray-400',
    };
@endphp

<a href="{{ $href }}" class="font-semibold {{ $linkClass }}">{{ $order->order_number }}</a>
@if ($showDate)
    <p class="mt-0.5 text-xs {{ $dateClass }}">{{ $order->created_at->format($dateFormat) }}</p>
@endif
