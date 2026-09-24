{{-- Section title + optional "See all" link. Vars: $title, $id (heading id), $href = null, $linkLabel = 'See all', $count = null --}}
<div class="flex items-end justify-between gap-3">
    <h2 id="{{ $id }}" class="text-base font-semibold text-gray-900">
        {{ $title }}
        @if (($count ?? null) !== null)
            <span class="ml-1 text-sm font-normal text-gray-500">({{ $count }})</span>
        @endif
    </h2>
    @if ($href ?? null)
        <a href="{{ $href }}" class="mbui-anchor shrink-0 text-sm">{{ $linkLabel ?? 'See all' }}</a>
    @endif
</div>
