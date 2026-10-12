@php
    $money = fn ($cents, $cur) => strtoupper($cur).' '.number_format($cents / 100, $cur === 'tzs' ? 0 : 2);
    $canManage = auth()->user()->can('vpn.manage');
@endphp
<x-layouts.admin title="VPN payments" header="Mbunie VPN">
    @include('admin.vpn._header', ['title' => 'VPN payments', 'subtitle' => 'Payments made in the VPN app. Check the screenshot, then approve to switch the VPN on at once.'])

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    <form method="GET" class="mt-4 vpn-row">
        @foreach (['review' => 'To review', 'pending' => 'All pending', 'paid' => 'Paid', 'failed' => 'Rejected / failed'] as $key => $label)
            <a class="vpn-btn {{ $status === $key ? 'primary' : '' }}" href="{{ route('admin.vpn.payments', array_filter(['status' => $key, 'q' => $q])) }}">{{ $label }}</a>
        @endforeach
        <input type="hidden" name="status" value="{{ $status }}">
        <input class="vpn-input" style="max-width:18rem" type="search" name="q" value="{{ $q }}" placeholder="Customer email or phone">
        <button class="vpn-btn" type="submit">Search</button>
    </form>

    @if ($result)
        <div class="mbui-card mt-4" style="overflow:visible">
            <table class="vpn-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th class="vpn-md">Proof</th>
                        <th class="vpn-lg">Date</th>
                        <th style="text-align:right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result['data'] as $i)
                        @php $open = in_array($i['status'], ['pending', 'pending_review'], true); @endphp
                        <tr>
                            <td class="vpn-wrap">
                                @if ($i['customer'])
                                    <a class="vpn-link" href="{{ route('admin.vpn.customers.show', $i['customer']['id']) }}">{{ $i['customer']['email'] ?? $i['customer']['phone'] }}</a>
                                @endif
                                <span class="sub">{{ ucfirst($i['method'] ?? $i['provider']) }}@if ($i['user_note']) · “{{ $i['user_note'] }}”@endif</span>
                            </td>
                            <td>
                                {{ $money($i['amount_cents'], $i['currency']) }}
                                <span class="sub">{{ strtoupper($i['plan']) }} · <x-mbui.status-badge :status="$i['status'] === 'pending_review' ? 'pending' : $i['status']" /></span>
                                @if ($i['review_note'])<span class="sub">{{ $i['review_note'] }}</span>@endif
                            </td>
                            <td class="vpn-md">
                                @if ($i['has_proof'])
                                    <a href="{{ route('admin.vpn.payments.proof', $i['id']) }}" target="_blank" rel="noopener" title="Open the screenshot">
                                        <img class="vpn-thumb" src="{{ route('admin.vpn.payments.proof', $i['id']) }}" alt="Payment screenshot" loading="lazy">
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="vpn-lg">{{ \Illuminate\Support\Carbon::parse($i['paid_at'] ?? $i['created_at'])->format('d M Y H:i') }}</td>
                            <td class="vpn-actions" style="text-align:right;white-space:nowrap">
                                @if ($i['has_proof'])
                                    <a class="vpn-btn vpn-md-hide" href="{{ route('admin.vpn.payments.proof', $i['id']) }}" target="_blank" rel="noopener" style="display:none">Proof</a>
                                @endif
                                @if ($canManage && $open)
                                    <form method="POST" action="{{ route('admin.vpn.payments.approve', $i['id']) }}"
                                          onsubmit="return confirm('Approve {{ $money($i['amount_cents'], $i['currency']) }} and switch the VPN on?')">
                                        @csrf
                                        <button class="vpn-btn green" type="submit">Approve</button>
                                    </form>
                                    <details>
                                        <summary class="vpn-btn red-o">Reject</summary>
                                        <div class="vpn-pop" style="text-align:left;white-space:normal">
                                            <form method="POST" action="{{ route('admin.vpn.payments.reject', $i['id']) }}">
                                                @csrf
                                                <label>Reason (the customer sees this)</label>
                                                <input class="vpn-input" name="reason" maxlength="300" required placeholder="e.g. Amount does not match">
                                                <button class="vpn-btn red mt-2" type="submit">Reject payment</button>
                                            </form>
                                        </div>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gray-500">{{ $status === 'review' ? 'Nothing to review. 🎉' : 'No payments.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.vpn._pager', ['meta' => $result['meta']])
    @endif

    <style>@media (max-width: 767px) { .vpn-md-hide { display: inline-flex !important; } }</style>
</x-layouts.admin>
