<x-layouts.admin title="Mbunie VPN" header="Mbunie VPN">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Mbunie VPN</h1>
            <p class="mt-1 text-sm text-gray-500">Takwimu za moja kwa moja kutoka vpn.mbuniehub.com, na oda za VPN zilizolipiwa hapa EduHub.</p>
        </div>
        <a href="https://vpn.mbuniehub.com/admin" target="_blank" rel="noopener" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">VPN admin panel &rarr;</a>
    </div>

    @if (session('success'))
        <x-mbui.alert type="success" class="mt-4">{{ session('success') }}</x-mbui.alert>
    @endif
    @if (session('error'))
        <x-mbui.alert type="error" class="mt-4">{{ session('error') }}</x-mbui.alert>
    @endif
    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    @if ($stats)
        @php
            $tzs = $stats['payments']['revenue_this_month']['tzs']['cents'] ?? 0;
            $usd = $stats['payments']['revenue_this_month']['usd']['cents'] ?? 0;
        @endphp
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-mbui.stats-card title="Wanaotumia VPN sasa" :value="$stats['online_now']['devices']"
                :trend="'Wateja '.$stats['online_now']['customers'].' · vifaa '.$stats['online_now']['devices']" />
            <x-mbui.stats-card title="Subscriptions hai" :value="$stats['subscriptions']['active']"
                :trend="$stats['subscriptions']['expiring_3d'].' zinaisha ndani ya siku 3'" />
            <x-mbui.stats-card title="Wateja wote" :value="$stats['customers']"
                :trend="$stats['subscriptions']['expired'].' wameisha muda · '.$stats['subscriptions']['suspended'].' wamesimamishwa'" />
            <x-mbui.stats-card title="Mapato mwezi huu" :value="'TSh '.number_format($tzs / 100)"
                :trend="($usd ? '$'.number_format($usd / 100, 2).' · ' : '').$stats['payments']['pending'].' malipo yanasubiri'" />
        </div>

        <x-mbui.table title="Wanaotumia sasa hivi" class="mt-6">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Mteja</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Kifaa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Protocol</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Tangu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($stats['online_now']['list'] as $d)
                    <tr>
                        <td class="px-6 py-3 text-sm text-gray-900">{{ $d['customer'] ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ ucfirst($d['platform'] ?? '—') }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $d['protocol'] ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $d['connected_since'] ? \Illuminate\Support\Carbon::parse($d['connected_since'])->diffForHumans() : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">Hakuna aliyeconnect kwa sasa.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-mbui.table title="Malipo ya karibuni (VPN)" class="lg:col-span-2">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mteja</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kiasi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Njia</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Hali</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($stats['recent_payments'] as $p)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $p['customer'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $p['plan'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ strtoupper($p['currency']) }} {{ number_format($p['amount_cents'] / 100, $p['currency'] === 'tzs' ? 0 : 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $p['provider'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-mbui.badge :appearance="match ($p['status']) { 'paid' => 'success', 'failed', 'expired' => 'danger', default => 'warning' }">{{ $p['status'] }}</x-mbui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Hakuna malipo bado.</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>

            <x-mbui.table title="Nodes">
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($stats['nodes'] as $n)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $n['name'] }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <x-mbui.badge :appearance="$n['status'] === 'online' ? 'success' : 'danger'">{{ $n['status'] }}</x-mbui.badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    @endif

    <x-mbui.table title="Oda za VPN zilizolipiwa hapa EduHub" class="mt-6">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Oda</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mteja</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Plan</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Hali ya VPN</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse ($orders as $o)
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">#{{ $o->order_number }}<div class="text-xs text-gray-500">{{ $o->status }}</div></td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $o->user?->email }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $o->plan?->name }} <span class="text-xs text-gray-400">({{ $o->plan?->mvpn_plan_code ?? 'hakuna code!' }})</span></td>
                    <td class="px-4 py-3 text-sm">
                        @if ($o->vpn_activated_at)
                            <x-mbui.badge appearance="success">Imewashwa</x-mbui.badge>
                            <div class="mt-1 text-xs text-gray-500">Inaisha {{ $o->vpn_expires_at?->format('d M Y H:i') ?? '—' }}</div>
                        @elseif ($o->status === 'confirmed')
                            <x-mbui.badge appearance="danger">Haijawashwa</x-mbui.badge>
                            <div class="mt-1 max-w-xs truncate text-xs text-red-600" title="{{ $o->vpn_activation_error }}">{{ $o->vpn_activation_error }} ({{ $o->vpn_activation_attempts }} majaribio)</div>
                        @else
                            <x-mbui.badge>Inasubiri malipo</x-mbui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ($o->status === 'confirmed' && ! $o->vpn_activated_at)
                            <form method="POST" action="{{ route('admin.vpn.orders.retry', $o) }}">
                                @csrf
                                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Jaribu tena</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Bado hakuna oda za VPN. Unda bidhaa ya aina "vpn" kwenye Products, na weka MVPN plan code kwenye kila plan.</td></tr>
            @endforelse
        </tbody>
    </x-mbui.table>
</x-layouts.admin>
