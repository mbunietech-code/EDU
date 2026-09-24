@props(['status'])

@php
    $status = strtolower((string) $status);
@endphp

@if ($status === 'live')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md bg-red-600 px-2 py-1 text-xs font-semibold tracking-wide text-white']) }}>
        <span class="relative flex h-2 w-2" aria-hidden="true">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
        </span>
        LIVE
    </span>
@elseif ($status === 'scheduled')
    <x-mbui.badge appearance="info" {{ $attributes }}>Scheduled</x-mbui.badge>
@elseif ($status === 'completed')
    <x-mbui.badge appearance="success" {{ $attributes }}>Completed</x-mbui.badge>
@elseif ($status === 'cancelled')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md bg-white px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-300']) }}>Cancelled</span>
@else
    <x-mbui.badge appearance="neutral" {{ $attributes }}>{{ ucfirst($status ?: 'draft') }}</x-mbui.badge>
@endif
