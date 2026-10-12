<x-layouts.admin title="Mbunie VPN" header="Mbunie VPN">

    @include('admin.vpn._header', ['title' => 'Mbunie VPN', 'subtitle' => 'Live from vpn.mbuniehub.com, plus the VPN orders paid here on EduHub.'])

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    @if ($stats)
        @php
            // Every currency the VPN took this month, plus one total in TSh at today's rate.
            $revenue = $stats['payments']['revenue_this_month'] ?? [];
            $perTzs = ['tzs' => 1.0, 'usd' => (float) ($rates['USD'] ?? 0), 'cny' => (float) ($rates['CNY'] ?? 0)];
            $totalTzs = 0.0;
            $parts = [];
            $count = 0;
            foreach ($revenue as $cur => $r) {
                $amount = $r['cents'] / 100;
                $count += $r['count'];
                if (($perTzs[$cur] ?? 0) > 0) {
                    $totalTzs += $amount / $perTzs[$cur];
                }
                $parts[] = match ($cur) {
                    'usd' => '$'.number_format($amount, 2),
                    'cny' => '¥'.number_format($amount, $amount == floor($amount) ? 0 : 2),
                    'tzs' => 'TSh '.number_format($amount),
                    default => strtoupper($cur).' '.number_format($amount, 2),
                };
            }
            $onlyTzs = array_keys($revenue) === [] || array_keys($revenue) === ['tzs'];
        @endphp
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-mbui.stats-card title="Using the VPN now" :value="$stats['online_now']['devices']"
                :trend="$stats['online_now']['customers'].' customers · '.$stats['online_now']['devices'].' devices'" />
            <x-mbui.stats-card title="Active subscriptions" :value="$stats['subscriptions']['active']"
                :trend="$stats['subscriptions']['expiring_3d'].' end within 3 days'" />
            <x-mbui.stats-card title="All customers" :value="$stats['customers']"
                :trend="$stats['subscriptions']['expired'].' expired · '.$stats['subscriptions']['suspended'].' suspended'" />
            <x-mbui.stats-card title="Revenue this month" :value="($onlyTzs ? '' : '≈ ').'TSh '.number_format(round($totalTzs))"
                :trend="($parts ? implode(' + ', $parts).' · '.$count.' paid · ' : '').$stats['payments']['pending'].' payments waiting'" />
        </div>

        <x-mbui.table title="Online right now" class="mt-6">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Device</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Protocol</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Since</th>
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
                    <tr><td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">Nobody is connected right now.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-mbui.table title="Recent VPN payments" class="lg:col-span-2">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Method</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
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
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No payments yet.</td></tr>
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

    <x-mbui.table title="VPN orders paid on EduHub" class="mt-6">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Order</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Customer</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Plan</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">VPN</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse ($orders as $o)
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-900">#{{ $o->order_number }}<div class="text-xs text-gray-500">{{ $o->status }}</div></td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $o->user?->email }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $o->plan?->name }} <span class="text-xs text-gray-400">({{ $o->plan?->mvpn_plan_code ?? 'no VPN plan code!' }})</span></td>
                    <td class="px-4 py-3 text-sm">
                        @if ($o->vpn_activated_at)
                            <x-mbui.badge appearance="success">Active</x-mbui.badge>
                            <div class="mt-1 text-xs text-gray-500">Until {{ $o->vpn_expires_at?->format('d M Y H:i') ?? '—' }}</div>
                        @elseif ($o->status === 'confirmed')
                            <x-mbui.badge appearance="danger">Not activated</x-mbui.badge>
                            <div class="mt-1 max-w-xs truncate text-xs text-red-600" title="{{ $o->vpn_activation_error }}">{{ $o->vpn_activation_error }} ({{ $o->vpn_activation_attempts }} attempts)</div>
                        @else
                            <x-mbui.badge>Waiting for payment</x-mbui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ($o->status === 'confirmed' && ! $o->vpn_activated_at)
                            <form method="POST" action="{{ route('admin.vpn.orders.retry', $o) }}">
                                @csrf
                                <button class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Retry</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No VPN orders yet. Create a product of type "vpn" under Products and set the VPN plan code on each plan.</td></tr>
            @endforelse
        </tbody>
    </x-mbui.table>
</x-layouts.admin>
