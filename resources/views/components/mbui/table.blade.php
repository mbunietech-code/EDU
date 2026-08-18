@props(['title' => null, 'action' => null])

<div {{ $attributes->merge(['class' => 'mbui-card overflow-hidden']) }}>
    @if ($title)
        <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            @if ($action)
                <div>{{ $action }}</div>
            @endif
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            {{ $slot }}
        </table>
    </div>
</div>