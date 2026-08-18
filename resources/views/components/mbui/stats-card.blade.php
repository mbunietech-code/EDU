@props(['title' => null, 'value' => null, 'trend' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'mbui-card p-6']) }}>
    <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-gray-500">{{ $title }}</p>
        @if ($icon)
            <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600">
                {{ $icon }}
            </div>
        @endif
    </div>
    <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">{{ $value }}</p>
    @if ($trend)
        <p class="mt-1 text-xs text-gray-500">{{ $trend }}</p>
    @endif
</div>