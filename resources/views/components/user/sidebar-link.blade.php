@props(['route', 'label', 'badge' => null, 'active' => false])

<a href="{{ $route }}" {{ $attributes->merge(['class' => ($active ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white') . ' group flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium transition']) }}>
    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        {{ $slot }}
    </svg>
    <span class="flex-1">{{ $label }}</span>
    @if ($badge && $badge > 0)
        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">{{ $badge > 99 ? '99+' : $badge }}</span>
    @endif
</a>