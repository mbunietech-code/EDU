@php
    $fmt = fn ($iso, $f = 'd M Y H:i') => $iso ? \Illuminate\Support\Carbon::parse($iso)->format($f) : '—';
    $ago = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->diffForHumans() : 'never';
    $money = fn ($cents, $cur) => strtoupper($cur).' '.number_format($cents / 100, in_array($cur, ['tzs'], true) ? 0 : 2);
    $cust = $c['customer'] ?? null;
@endphp
<x-layouts.admin title="VPN customer" header="Mbunie VPN">
    @include('admin.vpn._header', ['title' => $cust ? ($cust['email'] ?? $cust['phone'] ?? 'Customer #'.$cust['id']) : 'VPN customer'])

    <p class="mt-3 text-sm"><a class="vpn-link" href="{{ route('admin.vpn.customers') }}">&larr; All customers</a></p>

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    @if ($cust)
        @php $canManage = auth()->user()->can('vpn.manage'); @endphp

        <div class="vpn-grid2 mt-4">
            {{-- Customer --}}
            <x-mbui.card>
                <h2 class="mbui-section-label">Customer</h2>
                <dl class="mt-3 space-y-1 text-sm">
                    <div><span class="text-gray-500">Name:</span> {{ $cust['name'] ?: '—' }}</div>
                    <div class="vpn-wrap"><span class="text-gray-500">Email:</span> {{ $cust['email'] ?? '—' }}</div>
                    <div><span class="text-gray-500">Phone:</span> {{ $cust['phone'] ?? '—' }}</div>
                    <div><span class="text-gray-500">EduHub account:</span> {{ $cust['eduhub_user_id'] ? '#'.$cust['eduhub_user_id'] : 'not linked' }}</div>
                    <div><span class="text-gray-500">Status:</span> <x-mbui.status-badge :status="$cust['status'] ?? 'active'" /></div>
                    <div><span class="text-gray-500">Joined:</span> {{ $fmt($cust['created_at'], 'd M Y') }}</div>
                </dl>
                @if ($canManage)
                    <div class="vpn-actions mt-4">
                        @if (($cust['status'] ?? 'active') === 'active')
                            <details>
                                <summary class="vpn-btn red-o">Suspend customer</summary>
                                <div class="vpn-pop" style="left:0;right:auto">
                                    <form method="POST" action="{{ route('admin.vpn.customers.status', $cust['id']) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="suspended">
                                        <p class="text-xs text-gray-600">Turns off all their VPN plans and signs them out of the app. They cannot sign in again until restored.</p>
                                        <label class="mt-2">Reason (optional)</label>
                                        <input class="vpn-input" name="reason" maxlength="200">
                                        <button class="vpn-btn red mt-2" type="submit">Suspend</button>
                                    </form>
                                </div>
                            </details>
                        @else
                            <form method="POST" action="{{ route('admin.vpn.customers.status', $cust['id']) }}">
                                @csrf
                                <input type="hidden" name="status" value="active">
                                <button class="vpn-btn green" type="submit">Restore customer</button>
                            </form>
                        @endif
                    </div>
                @endif
            </x-mbui.card>

            {{-- Grant a plan --}}
            <x-mbui.card>
                <h2 class="mbui-section-label">Give a plan</h2>
                <p class="mt-2 text-sm text-gray-600">For a gift, compensation or a payment received outside the system. Recorded as a zero-amount payment.</p>
                @if ($canManage)
                    <form method="POST" action="{{ route('admin.vpn.customers.grant', $cust['id']) }}" class="mt-3 space-y-2"
                          onsubmit="return confirm('Give this plan to {{ addslashes($cust['email'] ?? 'this customer') }}?')">
                        @csrf
                        <select class="vpn-input" name="plan_code" required>
                            @foreach ($plans as $p)
                                <option value="{{ $p['code'] }}" @disabled(! $p['is_active'])>{{ $p['name'] }} ({{ $p['days'] }} days){{ $p['is_active'] ? '' : ' — inactive' }}</option>
                            @endforeach
                        </select>
                        <input class="vpn-input" name="note" maxlength="200" placeholder="Note (optional)">
                        <button class="vpn-btn primary" type="submit">Give plan</button>
                    </form>
                @endif
            </x-mbui.card>
        </div>

        {{-- Subscriptions --}}
        <div class="mbui-card mt-6" style="overflow:visible">
            <div class="border-b border-gray-200 px-6 py-4"><h3 class="text-base font-semibold text-gray-900">Subscriptions</h3></div>
            <table class="vpn-table">
                <thead><tr><th>Plan</th><th>Until</th><th class="vpn-md">Devices</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($c['subscriptions'] as $s)
                        <tr>
                            <td><x-mbui.status-badge :status="$s['status']" /><span class="sub">{{ strtoupper($s['plan']) }}</span></td>
                            <td>{{ $fmt($s['expires_at']) }}<span class="sub">{{ $s['expires_at'] ? \Illuminate\Support\Carbon::parse($s['expires_at'])->diffForHumans() : '' }}</span></td>
                            <td class="vpn-md">@if ($s['online'])<span class="vpn-dot"></span>@endif{{ $s['devices'] }} / {{ $s['max_devices'] }}</td>
                            <td class="vpn-actions" style="text-align:right">
                                @if ($canManage)
                                    <details>
                                        <summary class="vpn-btn">Add days</summary>
                                        <div class="vpn-pop">
                                            <form method="POST" action="{{ route('admin.vpn.subscriptions.extend', $s['id']) }}">
                                                @csrf
                                                <label>Days to add</label>
                                                <input class="vpn-input" type="number" name="days" min="1" max="3650" value="30" required>
                                                <label class="mt-2">Note (optional)</label>
                                                <input class="vpn-input" name="note" maxlength="200">
                                                <button class="vpn-btn primary mt-2" type="submit">Add days</button>
                                            </form>
                                        </div>
                                    </details>
                                    @if ($s['status'] === 'suspended')
                                        <form method="POST" action="{{ route('admin.vpn.subscriptions.restore', $s['id']) }}">
                                            @csrf
                                            <button class="vpn-btn green" type="submit">Restore</button>
                                        </form>
                                    @else
                                        <details>
                                            <summary class="vpn-btn red-o">Suspend</summary>
                                            <div class="vpn-pop">
                                                <form method="POST" action="{{ route('admin.vpn.subscriptions.suspend', $s['id']) }}">
                                                    @csrf
                                                    <p class="text-xs text-gray-600">Turns the VPN off now. Restore gives it back with the days left.</p>
                                                    <label class="mt-2">Reason (optional)</label>
                                                    <input class="vpn-input" name="reason" maxlength="200">
                                                    <button class="vpn-btn red mt-2" type="submit">Suspend</button>
                                                </form>
                                            </div>
                                        </details>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-gray-500">No subscriptions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Devices --}}
        <div class="mbui-card mt-6" style="overflow:visible">
            <div class="border-b border-gray-200 px-6 py-4"><h3 class="text-base font-semibold text-gray-900">Devices</h3></div>
            @include('admin.vpn._devices_table', ['devices' => $c['devices'], 'showCustomer' => false, 'canManage' => $canManage])
        </div>

        {{-- Payments --}}
        <div class="mbui-card mt-6" style="overflow:visible">
            <div class="border-b border-gray-200 px-6 py-4"><h3 class="text-base font-semibold text-gray-900">Payments</h3></div>
            <table class="vpn-table">
                <thead><tr><th>Amount</th><th>Status</th><th class="vpn-md">Method</th><th class="vpn-lg">Date</th></tr></thead>
                <tbody>
                    @forelse ($c['invoices'] as $i)
                        <tr>
                            <td>{{ $money($i['amount_cents'], $i['currency']) }}<span class="sub">{{ strtoupper($i['plan']) }}</span></td>
                            <td><x-mbui.status-badge :status="$i['status'] === 'pending_review' ? 'pending' : $i['status']" />@if ($i['review_note'])<span class="sub">{{ $i['review_note'] }}</span>@endif</td>
                            <td class="vpn-md">{{ $i['method'] ?? $i['provider'] }}</td>
                            <td class="vpn-lg">{{ $fmt($i['paid_at'] ?? $i['created_at']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-gray-500">No payments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>
