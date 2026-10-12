@php $canManage = auth()->user()->can('vpn.manage'); @endphp
<x-layouts.admin title="VPN plans" header="Mbunie VPN">
    @include('admin.vpn._header', ['title' => 'VPN plans', 'subtitle' => 'Prices are in normal amounts (8 = $8.00, 55 = ¥55, 20000 = TSh 20,000). Changes show in the app straight away.'])

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    @php $vpnProducts = \App\Models\Product::where('type', 'vpn')->get(['id', 'name']); @endphp
    <div class="mbui-card mt-4 vpn-row" style="padding:.75rem 1rem;justify-content:space-between">
        <span class="text-sm text-gray-700">
            @if ($vpnProducts->isNotEmpty())
                Sold on EduHub as <strong>{{ $vpnProducts->pluck('name')->implode(', ') }}</strong>: saving a plan here updates its name, days and TZS price there too. Plans off sale or without a TZS price are hidden on EduHub.
            @else
                No EduHub product of type “vpn” yet, so these plans are only sold in the app.
            @endif
        </span>
        @if ($canManage && $vpnProducts->isNotEmpty())
            <form method="POST" action="{{ route('admin.vpn.plans.sync') }}">
                @csrf
                <button class="vpn-btn" type="submit">Update EduHub now</button>
            </form>
        @endif
    </div>

    @php
        $usdPerTzs = (float) ($rates['USD'] ?? 0);
        $cnyPerTzs = (float) ($rates['CNY'] ?? 0);
    @endphp
    @if ($canManage && $usdPerTzs > 0 && $cnyPerTzs > 0)
        <div class="mbui-card mt-4 vpn-row" style="padding:.75rem 1rem;justify-content:space-between">
            <span class="text-sm text-gray-700">
                Today's rate: <strong>$1 = ¥{{ number_format($cnyPerTzs / $usdPerTzs, 2) }} = TSh {{ number_format(1 / $usdPerTzs) }}</strong>
            </span>
            <label class="text-sm text-gray-700"><input type="checkbox" id="vpn-autoconvert" checked> Convert prices automatically</label>
        </div>
        <p class="mt-2 text-xs text-gray-500">Type a price in any currency and the other two fill in at today's rate (USD to the cent, CNY to the whole yuan, TZS to the nearest 100). You can still change any of them by hand before saving.</p>
    @endif

    <div class="vpn-grid2 mt-4">
        @foreach ($plans as $p)
            <x-mbui.card>
                <form method="POST" action="{{ route('admin.vpn.plans.update', $p['id']) }}" data-plan-prices>
                    @csrf
                    @method('PUT')
                    <div class="vpn-row" style="justify-content:space-between">
                        <h2 class="mbui-section-label">{{ strtoupper($p['code']) }}</h2>
                        <label class="text-sm text-gray-700"><input type="checkbox" name="is_active" value="1" @checked($p['is_active']) @disabled(! $canManage)> On sale</label>
                    </div>
                    <div class="mt-3 space-y-3" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                        <div style="grid-column:1/-1">
                            <label class="text-xs text-gray-600">Name</label>
                            <input class="vpn-input" name="name" value="{{ $p['name'] }}" required maxlength="80" @disabled(! $canManage)>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Days</label>
                            <input class="vpn-input" type="number" name="days" value="{{ $p['days'] }}" min="1" max="3650" required @disabled(! $canManage)>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Devices allowed</label>
                            <input class="vpn-input" type="number" name="max_devices" value="{{ $p['max_devices'] }}" min="1" max="20" required @disabled(! $canManage)>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Price USD ($)</label>
                            <input class="vpn-input" type="number" step="0.01" min="0" name="price_usd" value="{{ $p['price_usd'] }}" required @disabled(! $canManage)>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Price CNY (¥)</label>
                            <input class="vpn-input" type="number" step="0.01" min="0" name="price_cny" value="{{ $p['price_cny'] }}" required @disabled(! $canManage)>
                        </div>
                        <div style="grid-column:1/-1">
                            <label class="text-xs text-gray-600">Price TZS (TSh) — 0 hides TZS</label>
                            <input class="vpn-input" type="number" step="1" min="0" name="price_tzs" value="{{ $p['price_tzs'] }}" required @disabled(! $canManage)>
                        </div>
                    </div>
                    @if ($canManage)
                        <button class="vpn-btn primary mt-4" type="submit">Save {{ $p['name'] }}</button>
                    @endif
                </form>
            </x-mbui.card>
        @endforeach
    </div>

    @if ($canManage && $usdPerTzs > 0 && $cnyPerTzs > 0)
        <script>
            (() => {
                const perTzs = { usd: {{ $usdPerTzs }}, cny: {{ $cnyPerTzs }}, tzs: 1 };
                const round = {
                    usd: v => Math.round(v * 100) / 100,
                    cny: v => Math.max(1, Math.round(v)),
                    tzs: v => Math.round(v / 100) * 100,
                };
                const auto = document.getElementById('vpn-autoconvert');
                document.querySelectorAll('form[data-plan-prices]').forEach(form => {
                    const field = cur => form.querySelector(`[name=price_${cur}]`);
                    ['usd', 'cny', 'tzs'].forEach(from => {
                        field(from).addEventListener('input', () => {
                            const v = parseFloat(field(from).value);
                            if (!auto.checked || !(v > 0)) return;
                            const tzs = v / perTzs[from];
                            ['usd', 'cny', 'tzs'].filter(to => to !== from)
                                .forEach(to => field(to).value = round[to](tzs * perTzs[to]));
                        });
                    });
                });
            })();
        </script>
    @endif
</x-layouts.admin>
