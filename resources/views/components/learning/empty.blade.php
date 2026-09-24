@props(['title', 'message' => null, 'actionHref' => null, 'actionLabel' => null])

<x-mbui.card {{ $attributes }}>
    <x-mbui.empty-state :title="$title" :message="$message" />
    @if ($actionHref && $actionLabel)
        <div class="-mt-6 pb-6 text-center">
            <x-mbui.btn-link :href="$actionHref">{{ $actionLabel }}</x-mbui.btn-link>
        </div>
    @endif
</x-mbui.card>
