{{-- Mbunie VPN in the product grids. It is bought on vpn.mbuniehub.com,
     so the card goes straight to its download page. --}}
@props(['product' => null])

<a href="{{ \App\Models\Product::vpnStoreUrl() }}" target="_blank" rel="noopener"
   class="mbui-card group flex items-center gap-3 p-3 ring-1 ring-emerald-500/30 transition hover:shadow-md">
    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-emerald-200 bg-emerald-50">
        @if ($product?->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="Mbunie VPN" class="h-full w-full object-contain p-1.5">
        @else
            <svg class="h-7 w-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex items-center justify-between gap-2">
            <h3 class="truncate text-sm font-semibold text-gray-900 group-hover:text-indigo-600">Mbunie VPN</h3>
            <x-mbui.badge appearance="success" class="shrink-0">VPN</x-mbui.badge>
        </div>
        <p class="mt-1 text-xs text-gray-600">Fast, private internet that works in China and wherever you travel.</p>
        <p class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700">
            Get the app
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
        </p>
    </div>
</a>
