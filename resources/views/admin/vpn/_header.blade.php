{{-- Shared header for the Mbunie VPN admin pages: title, tabs, flash messages. --}}
<style>
    /* Local helpers (the app's Tailwind build is precompiled). */
    .vpn-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: .75rem; }
    .vpn-tab { border-radius: .5rem; padding: .45rem .8rem; font-size: .875rem; font-weight: 500; color: #374151; }
    .vpn-tab:hover { background: #f3f4f6; }
    .vpn-tab.on { background: #4f46e5; color: #fff; }
    .vpn-table { width: 100%; border-collapse: collapse; }
    .vpn-table th { padding: .7rem 1rem; text-align: left; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; background: #f9fafb; }
    .vpn-table td { padding: .75rem 1rem; font-size: .875rem; color: #111827; border-top: 1px solid #f3f4f6; vertical-align: top; }
    .vpn-table td .sub { display: block; color: #6b7280; font-size: .78rem; margin-top: .1rem; }
    .vpn-wrap { overflow-wrap: anywhere; }
    @media (max-width: 767px) { .vpn-md { display: none; } .vpn-table th, .vpn-table td { padding: .6rem .6rem; } }
    @media (max-width: 1023px) { .vpn-lg { display: none; } }
    .vpn-input { border: 1px solid #d1d5db; border-radius: .5rem; padding: .45rem .65rem; font-size: .875rem; width: 100%; background: #fff; }
    .vpn-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .vpn-actions form { display: inline; }
    .vpn-actions details { display: inline-block; position: relative; }
    .vpn-actions summary { list-style: none; cursor: pointer; }
    .vpn-actions summary::-webkit-details-marker { display: none; }
    .vpn-pop { position: absolute; right: 0; z-index: 20; margin-top: .4rem; width: 17rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; box-shadow: 0 12px 30px rgba(0,0,0,.12); padding: .85rem; }
    .vpn-pop label { display: block; font-size: .78rem; color: #4b5563; margin-bottom: .25rem; }
    .vpn-link { color: #4f46e5; font-weight: 500; }
    .vpn-link:hover { text-decoration: underline; }
    .vpn-btn { display: inline-flex; align-items: center; border-radius: .5rem; padding: .35rem .7rem; font-size: .8rem; font-weight: 600; border: 1px solid #d1d5db; background: #fff; color: #111827; }
    .vpn-btn:hover { background: #f9fafb; }
    .vpn-btn.primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .vpn-btn.green { background: #059669; border-color: #059669; color: #fff; }
    .vpn-btn.red { background: #dc2626; border-color: #dc2626; color: #fff; }
    .vpn-btn.red-o { color: #b91c1c; border-color: #fecaca; }
    .vpn-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; background: #10b981; margin-right: .3rem; }
    .vpn-grid2 { display: grid; gap: 1.5rem; }
    @media (min-width: 1024px) { .vpn-grid2 { grid-template-columns: 1fr 1fr; } }
    .vpn-thumb { width: 3.5rem; height: 3.5rem; object-fit: cover; border-radius: .4rem; border: 1px solid #e5e7eb; }
</style>

<div class="mbui-page-header">
    <div>
        <h1 class="mbui-title">{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
</div>

@php
    $tabs = [
        'admin.vpn.index' => 'Overview',
        'admin.vpn.customers' => 'Customers',
        'admin.vpn.devices' => 'Devices',
        'admin.vpn.payments' => 'Payments',
        'admin.vpn.plans' => 'Plans',
    ];
@endphp
<nav class="vpn-tabs" aria-label="Mbunie VPN">
    @foreach ($tabs as $route => $label)
        <a href="{{ route($route) }}" class="vpn-tab {{ request()->routeIs($route) || request()->routeIs($route.'.*') ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
