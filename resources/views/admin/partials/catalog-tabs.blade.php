{{-- Tabs for Products & accounts: products (with their plans), accounts we hand out, AI plans we bought. --}}
@php
    $catalogTabs = array_filter([
        ['Products', route('admin.products.index'), request()->routeIs('admin.products.*', 'admin.plans.*'), auth()->user()->can('products.view')],
        ['Accounts', route('admin.accounts.index'), request()->routeIs('admin.accounts.*') && ! request()->routeIs('admin.accounts.plans'), auth()->user()->can('accounts.view')],
        ['AI plans we bought', route('admin.accounts.plans'), request()->routeIs('admin.accounts.plans'), auth()->user()->can('accounts.view')],
    ], fn ($tab) => $tab[3]);
@endphp
<nav class="mt-4 -mb-px flex gap-6 overflow-x-auto border-b border-gray-200" aria-label="Products and accounts">
    @foreach ($catalogTabs as [$label, $href, $active])
        <a href="{{ $href }}" @if ($active) aria-current="page" @endif
            class="shrink-0 border-b-2 px-1 pb-3 text-sm font-semibold {{ $active ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
