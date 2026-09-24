@props(['percent' => 0, 'label' => null, 'size' => 'sm'])

@php
    $percent = (int) max(0, min(100, round((float) $percent)));
    $track = $size === 'md' ? 'h-2.5' : 'h-1.5';
    $fill = $percent >= 100 ? 'bg-emerald-500' : 'bg-indigo-600';
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($label)
        <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
            <span>{{ $label }}</span>
            <span class="font-medium text-gray-700">{{ $percent }}%</span>
        </div>
    @endif
    <div class="{{ $track }} w-full overflow-hidden rounded-full bg-gray-200"
        role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
        aria-label="{{ $label ?? 'Progress' }}">
        <div class="{{ $track }} {{ $fill }} rounded-full transition-all" style="width: {{ $percent }}%"></div>
    </div>
</div>
