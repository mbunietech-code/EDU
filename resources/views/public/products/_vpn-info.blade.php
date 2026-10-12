{{-- Extra content for Mbunie VPN products. Shown under the product's own text. --}}
@if (! $product->features)
    <div class="mt-8">
        <h2 class="mbui-title text-lg">Key features</h2>
        <ul class="mt-4 space-y-2">
            @foreach ([
                'Works in China, and picks the fastest route for your network by itself',
                'Apps for Android and Windows',
                'Switches on automatically as soon as your payment is confirmed',
                'Use it on more than one device (see each plan)',
                'Renew any time: new days are added to the days you have left',
            ] as $feature)
                <li class="flex items-start gap-3 text-sm text-gray-700">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $feature }}
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-8">
    <h2 class="mbui-title text-lg">How it works</h2>
    <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-gray-700">
        <li>Choose a plan and pay.</li>
        <li>Your VPN switches on automatically. Your order page shows the app downloads.</li>
        <li>Install the app, sign in with this account's email (we send you a one-time code) and tap <strong>Connect</strong>.</li>
    </ol>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ config('services.mvpn.download_android') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
            <svg width="18" height="18" viewBox="0 0 64 64" aria-hidden="true"><g fill="currentColor"><path d="M17 26a15 13 0 0 1 30 0z"/><rect x="17" y="28" width="30" height="21" rx="3"/><rect x="10" y="28" width="5" height="16" rx="2.5"/><rect x="49" y="28" width="5" height="16" rx="2.5"/><rect x="23" y="46" width="5" height="11" rx="2.5"/><rect x="36" y="46" width="5" height="11" rx="2.5"/></g></svg>
            Android app
        </a>
        <a href="{{ config('services.mvpn.download_windows') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
            <svg width="16" height="16" viewBox="0 0 64 64" aria-hidden="true"><path fill="currentColor" d="M6 12.2 27 9.3v20.3H6zM29.4 9 58 5v24.6H29.4zM6 32h21v20.3L6 49.4zM29.4 32H58v26.6l-28.6-4z"/></svg>
            Windows app
        </a>
    </div>
    <p class="mt-2 text-xs text-gray-500">You can install the app before buying; it works once your plan is active.</p>
</div>
