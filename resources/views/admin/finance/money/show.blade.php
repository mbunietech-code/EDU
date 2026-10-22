<x-layouts.finance :title="$account->name" header="Finance">

    <div class="mbui-page-header">
        <div>
            <a href="{{ route('admin.finance.money.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Money accounts</a>
            <h1 class="mbui-title mt-1">{{ $account->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $account->typeLabel() }}@if ($account->number) · {{ $account->number }}@endif
                @if ($methodLabels) · Customer payments: {{ implode(', ', $methodLabels) }}@endif
                @unless ($account->is_active) · Archived @endunless
            </p>
        </div>
        <div class="flex items-center gap-3">
            <x-mbui.icon-link :href="route('admin.finance.money.edit', $account)" />
            <div class="text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Balance</p>
                <p class="text-2xl font-bold tabular-nums {{ $balance >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($balance) }}</p>
            </div>
        </div>
    </div>

    {{-- Check against the bank / mobile money statement: nothing is saved. --}}
    <div class="mt-6 mbui-card p-5">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <x-input-label for="recon" value="Balance on your bank / wallet statement (TZS)" />
                <x-text-input id="recon" class="mbui-input mt-1 w-56" type="number" step="0.01" inputmode="decimal" placeholder="e.g. {{ (int) $balance }}" />
            </div>
            <p id="recon-result" class="pb-2 text-sm text-gray-500" aria-live="polite">Type the statement balance to see if it matches.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Date</th>
                    <th class="mbui-th">Description</th>
                    <th class="mbui-th">Ref</th>
                    <th class="mbui-th text-right">Debit (in)</th>
                    <th class="mbui-th text-right">Credit (out)</th>
                    <th class="mbui-th text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr class="bg-gray-50/50">
                    <td class="mbui-td text-gray-500">{{ $account->opening_date?->format('d M Y') ?? '—' }}</td>
                    <td class="mbui-td font-medium text-gray-700">Opening balance</td>
                    <td class="mbui-td"></td>
                    <td class="mbui-td"></td>
                    <td class="mbui-td"></td>
                    <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format($account->opening_balance) }}</td>
                </tr>
                @forelse ($rows as $r)
                    <tr>
                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ $r['date']->format('d M Y') }}</td>
                        <td class="mbui-td whitespace-normal">
                            @if ($r['url'])
                                <a href="{{ $r['url'] }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $r['label'] }}</a>
                            @else
                                <span class="font-medium text-gray-900">{{ $r['label'] }}</span>
                            @endif
                            @if ($r['detail'])
                                <p class="text-xs text-gray-500">{{ $r['detail'] }}</p>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal text-xs text-gray-500">{{ $r['ref'] }}</td>
                        <td class="mbui-td text-right tabular-nums text-emerald-700">{{ $r['amount'] > 0 ? number_format($r['amount']) : '' }}</td>
                        <td class="mbui-td text-right tabular-nums text-red-600">{{ $r['amount'] < 0 ? number_format(-$r['amount']) : '' }}</td>
                        <td class="mbui-td text-right font-semibold tabular-nums {{ $r['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">{{ number_format($r['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-500">No money in or out yet. Tick its customer payment methods, or pick this account on expenses, capital and repayments.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <script>
        (function () {
            var input = document.getElementById('recon');
            var out = document.getElementById('recon-result');
            var book = {{ json_encode((float) $balance) }};
            var fmt = function (n) { return 'TZS ' + Math.round(n).toLocaleString('en-US'); };
            input.addEventListener('input', function () {
                if (input.value === '') { out.textContent = 'Type the statement balance to see if it matches.'; out.className = 'pb-2 text-sm text-gray-500'; return; }
                var diff = Math.round((parseFloat(input.value) - book) * 100) / 100;
                if (diff === 0) { out.textContent = 'Matches the statement.'; out.className = 'pb-2 text-sm font-semibold text-emerald-700'; return; }
                out.textContent = (diff > 0 ? 'Statement has ' + fmt(diff) + ' more: a payment in is not recorded here.' : 'Statement has ' + fmt(-diff) + ' less: money out is not recorded here (expense, fee or transfer).');
                out.className = 'pb-2 text-sm font-semibold text-amber-700';
            });
        })();
    </script>

</x-layouts.finance>
