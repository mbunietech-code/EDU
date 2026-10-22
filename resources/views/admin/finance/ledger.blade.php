<x-layouts.finance title="Ledger" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Ledger</h1>
            <p class="mt-1 text-sm text-gray-500">Every movement with both sides: <strong>Debit</strong> is where the money went, <strong>Credit</strong> is where it came from. The two totals are always equal.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label for="ledger-month" class="text-sm text-gray-600">Month</label>
            <select id="ledger-month" name="month" class="mbui-input" onchange="this.form.submit()">
                <option value="">All time</option>
                @foreach ($months as $m)
                    <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m)->format('F Y') }}</option>
                @endforeach
            </select>
            <noscript><x-mbui.button type="submit" variant="secondary">Show</x-mbui.button></noscript>
        </form>
    </div>

    {{-- Trial balance: one line per ledger account. --}}
    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Trial balance</h2>
            <p class="mt-1 text-sm text-gray-500">Cash accounts show what is left in each; Sales, Capital and Loans show where money came from; Expenses show where it went.</p>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Account</th>
                    <th class="mbui-th text-right">Debit</th>
                    <th class="mbui-th text-right">Credit</th>
                    <th class="mbui-th text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @php($group = null)
                @forelse ($trial as $t)
                    @if ($t['group'] !== $group)
                        @php($group = $t['group'])
                        <tr class="bg-gray-50/60"><td colspan="4" class="mbui-td py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $group }}</td></tr>
                    @endif
                    <tr class="{{ $t['account'] === \App\Services\Finance\LedgerService::UNASSIGNED_CASH ? 'bg-amber-50/60' : '' }}">
                        <td class="mbui-td whitespace-normal">{{ \Illuminate\Support\Str::after($t['account'], ': ') }}</td>
                        <td class="mbui-td text-right tabular-nums">{{ $t['dr'] ? number_format($t['dr']) : '' }}</td>
                        <td class="mbui-td text-right tabular-nums">{{ $t['cr'] ? number_format($t['cr']) : '' }}</td>
                        <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format(abs($t['balance'])) }} <span class="text-xs font-normal text-gray-500">{{ $t['balance'] >= 0 ? 'Dr' : 'Cr' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-500">Nothing recorded for this period.</td></tr>
                @endforelse
            </tbody>
            @if ($trial->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-gray-300 font-semibold">
                        <td class="mbui-td">Total</td>
                        <td class="mbui-td text-right tabular-nums">{{ number_format($trial->sum('dr')) }}</td>
                        <td class="mbui-td text-right tabular-nums">{{ number_format($trial->sum('cr')) }}</td>
                        <td class="mbui-td text-right text-xs font-normal {{ round($trial->sum('dr') - $trial->sum('cr'), 2) == 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ round($trial->sum('dr') - $trial->sum('cr'), 2) == 0 ? 'Balanced' : 'Not balanced' }}</td>
                    </tr>
                </tfoot>
            @endif
        </x-mbui.table>
        @if ($trial->contains('account', \App\Services\Finance\LedgerService::UNASSIGNED_CASH))
            <p class="border-t border-gray-200 px-6 py-3 text-sm text-amber-700">"Account not set" holds money recorded without a money account. Assign it under <a href="{{ route('admin.finance.money.index') }}" class="font-semibold underline">Money accounts</a> to see which account it is in.</p>
        @endif
    </div>

    {{-- Journal: every movement, oldest first. --}}
    <div class="mt-8 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Journal</h2>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Date</th>
                    <th class="mbui-th">Description</th>
                    <th class="mbui-th">Debit (Dr)</th>
                    <th class="mbui-th">Credit (Cr)</th>
                    <th class="mbui-th text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($journal as $l)
                    <tr>
                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ $l['date']->format('d M Y') }}</td>
                        <td class="mbui-td whitespace-normal">
                            @if ($l['url'])
                                <a href="{{ $l['url'] }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $l['description'] }}</a>
                            @else
                                <span class="font-medium text-gray-900">{{ $l['description'] }}</span>
                            @endif
                            @if ($l['ref'])
                                <p class="text-xs text-gray-500">{{ $l['ref'] }}</p>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-normal text-sm text-sky-800">{{ $l['dr'] }}</td>
                        <td class="mbui-td whitespace-normal text-sm text-orange-800">{{ $l['cr'] }}</td>
                        <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format($l['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-500">Nothing recorded for this period.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.finance>
