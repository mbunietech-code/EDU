{{-- Prev / next for a partner-API page: expects $meta = {page, last_page, total}. --}}
@if (($meta['last_page'] ?? 1) > 1)
    <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
        <span>Page {{ $meta['page'] }} of {{ $meta['last_page'] }} · {{ $meta['total'] }} total</span>
        <span class="vpn-row">
            @if ($meta['page'] > 1)
                <a class="vpn-btn" href="{{ request()->fullUrlWithQuery(['page' => $meta['page'] - 1]) }}">&larr; Previous</a>
            @endif
            @if ($meta['page'] < $meta['last_page'])
                <a class="vpn-btn" href="{{ request()->fullUrlWithQuery(['page' => $meta['page'] + 1]) }}">Next &rarr;</a>
            @endif
        </span>
    </div>
@endif
