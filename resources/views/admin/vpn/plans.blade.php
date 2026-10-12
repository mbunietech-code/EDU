@php $canManage = auth()->user()->can('vpn.manage'); @endphp
<x-layouts.admin title="VPN plans" header="Mbunie VPN">
    @include('admin.vpn._header', ['title' => 'VPN plans', 'subtitle' => 'Prices are in normal amounts (8 = $8.00, 55 = ¥55, 20000 = TSh 20,000). Changes show in the app straight away.'])

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    <div class="vpn-grid2 mt-4">
        @foreach ($plans as $p)
            <x-mbui.card>
                <form method="POST" action="{{ route('admin.vpn.plans.update', $p['id']) }}">
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
</x-layouts.admin>
