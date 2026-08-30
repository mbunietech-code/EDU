@props(['class' => 'h-9 w-9', 'rounded' => 'rounded-lg'])

@php $logo = \App\Support\Branding::logoUrl(); @endphp

@if ($logo)
    <img src="{{ $logo }}" alt="{{ config('app.name', 'MbunieEduHub') }} logo"
         {{ $attributes->merge(['class' => trim($class.' '.$rounded.' object-contain')]) }}>
@else
    <span {{ $attributes->merge(['class' => trim($class.' '.$rounded.' flex items-center justify-center bg-indigo-600 font-bold text-white')]) }}>M</span>
@endif
