<x-layouts.admin title="VPN customers" header="Mbunie VPN">
    @include('admin.vpn._header', ['title' => 'VPN customers', 'subtitle' => 'Everyone with a Mbunie VPN account. Open a customer to add days, suspend, grant a plan or remove a device.'])

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    <form method="GET" class="mt-4 vpn-row">
        <input class="vpn-input" style="max-width:22rem" type="search" name="q" value="{{ $q }}" placeholder="Search email, phone or name">
        <button class="vpn-btn primary" type="submit">Search</button>
        @if ($q)
            <a class="vpn-btn" href="{{ route('admin.vpn.customers') }}">Clear</a>
        @endif
    </form>

    @if ($result)
        <div class="mbui-card mt-4 overflow-hidden">
            <table class="vpn-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th class="vpn-md">Devices</th>
                        <th class="vpn-lg">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result['data'] as $c)
                        @php $s = $c['subscription']; @endphp
                        <tr>
                            <td class="vpn-wrap">
                                <a class="vpn-link" href="{{ route('admin.vpn.customers.show', $c['id']) }}">{{ $c['email'] ?? $c['phone'] ?? 'Customer #'.$c['id'] }}</a>
                                <span class="sub">
                                    {{ $c['name'] ?: '—' }}
                                    @if ($c['eduhub_user_id']) · EduHub #{{ $c['eduhub_user_id'] }} @endif
                                    @if (($c['status'] ?? 'active') !== 'active') · <strong style="color:#b45309">{{ ucfirst($c['status']) }}</strong> @endif
                                </span>
                            </td>
                            <td>
                                @if ($s)
                                    <x-mbui.status-badge :status="$s['status']" />
                                    <span class="sub">{{ strtoupper($s['plan']) }}@if ($s['expires_at']) · until {{ \Illuminate\Support\Carbon::parse($s['expires_at'])->format('d M Y') }}@endif</span>
                                @else
                                    <span class="sub">No plan</span>
                                @endif
                            </td>
                            <td class="vpn-md">
                                @if ($s)
                                    @if ($s['online'])<span class="vpn-dot" title="Online now"></span>@endif{{ $s['devices'] }} / {{ $s['max_devices'] }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="vpn-lg">{{ $c['created_at'] ? \Illuminate\Support\Carbon::parse($c['created_at'])->format('d M Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-gray-500">No customers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.vpn._pager', ['meta' => $result['meta']])
    @endif
</x-layouts.admin>
