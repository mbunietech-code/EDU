{{-- Devices table. Needs $devices (partner API rows), $showCustomer, $canManage. --}}
<table class="vpn-table">
    <thead>
        <tr>
            <th>{{ $showCustomer ? 'Customer' : 'Device' }}</th>
            <th>VPN now</th>
            <th class="vpn-md">App</th>
            <th class="vpn-lg">Last error</th>
            <th style="text-align:right"><span class="sr-only">Actions</span></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($devices as $d)
            @php
                $device = trim(($d['model'] ?? '').' · '.($d['os_version'] ?? ''), ' ·') ?: ucfirst($d['platform'] ?? 'device');
                $when = match ($d['state']) {
                    'removed' => 'removed '.\Illuminate\Support\Carbon::parse($d['revoked_at'])->diffForHumans(),
                    'online' => $d['connected_at'] ? 'connected '.\Illuminate\Support\Carbon::parse($d['connected_at'])->diffForHumans() : 'online',
                    default => $d['last_seen_at'] ? 'seen '.\Illuminate\Support\Carbon::parse($d['last_seen_at'])->diffForHumans() : 'never seen',
                };
            @endphp
            <tr>
                <td class="vpn-wrap">
                    @if ($showCustomer && $d['customer'])
                        <a class="vpn-link" href="{{ route('admin.vpn.customers.show', $d['customer']['id']) }}">{{ $d['customer']['email'] ?? $d['customer']['phone'] }}</a>
                        <span class="sub">{{ $device }}</span>
                    @else
                        {{ $device }}
                    @endif
                </td>
                <td>
                    <x-mbui.badge :appearance="['online' => 'success', 'removed' => 'danger'][$d['state']] ?? 'neutral'">{{ ucfirst($d['state']) }}</x-mbui.badge>
                    <span class="sub">{{ $when }}</span>
                </td>
                <td class="vpn-md">{{ ucfirst($d['platform'] ?? '—') }} {{ $d['app_version'] }}<span class="sub">{{ $d['protocol'] ? 'protocol '.$d['protocol'] : '' }}</span></td>
                <td class="vpn-lg vpn-wrap" style="max-width:18rem">
                    @if ($d['last_error'])
                        <span style="color:#b91c1c" title="{{ $d['last_error'] }}">{{ \Illuminate\Support\Str::limit($d['last_error'], 90) }}</span>
                        <span class="sub">{{ \Illuminate\Support\Carbon::parse($d['last_error_at'])->diffForHumans() }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="vpn-actions" style="text-align:right">
                    @if ($canManage)
                        @if ($d['state'] === 'removed')
                            <form method="POST" action="{{ route('admin.vpn.devices.allow', $d['id']) }}">
                                @csrf
                                <button class="vpn-btn" type="submit">Allow again</button>
                            </form>
                        @else
                            <details>
                                <summary class="vpn-btn red-o">Remove</summary>
                                <div class="vpn-pop" style="text-align:left">
                                    <form method="POST" action="{{ route('admin.vpn.devices.remove', $d['id']) }}">
                                        @csrf
                                        <p class="text-xs text-gray-600">For a device the customer does not recognise. It is blocked for good, the customer is signed out everywhere and their VPN keys change. The real owner signs in again with an email code.</p>
                                        <label class="mt-2">Reason (optional)</label>
                                        <input class="vpn-input" name="reason" maxlength="200">
                                        <button class="vpn-btn red mt-2" type="submit">Remove device</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-gray-500">No devices.</td></tr>
        @endforelse
    </tbody>
</table>
