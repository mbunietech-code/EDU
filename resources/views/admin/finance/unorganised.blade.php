<x-layouts.finance title="Unorganised data" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Unorganised data</h1>
            <p class="mt-1 text-sm text-gray-500">
                @if ($start)
                    Every Finance record from before {{ $start->format('d M Y') }}, when Finance started counting again. Kept as it was and read-only: nothing here counts in the overview, accounts, ledger, budget or reports.
                @else
                    Finance counts every record. Set a start date to begin the books on a day and keep everything before it here, read-only.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if ($start)
                <a href="{{ route('admin.finance.unorganised.csv') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Download CSV</a>
            @endif
            <a href="{{ route('admin.finance.start.edit') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ $start ? 'Start date & opening balances' : 'Set a start date' }}</a>
        </div>
    </div>

    @if ($start)
        {{-- Tabs: summary, then one list per kind of record, then the old ledger. --}}
        <nav class="mt-6 overflow-x-auto border-b border-gray-200" aria-label="Unorganised data">
            <ul class="-mb-px flex min-w-max gap-1">
                @foreach ($tabs as $key => $label)
                    @php($active = $tab === $key)
                    <li>
                        <a href="{{ route('admin.finance.unorganised.index', array_filter(['tab' => $key === 'summary' ? null : $key, 'month' => in_array($key, ['sales', 'expenses', 'ledger'], true) ? $month : null])) }}"
                           @if ($active) aria-current="page" @endif
                           class="inline-flex items-center border-b-2 px-4 py-2.5 text-sm font-medium {{ $active ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900' }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if (in_array($tab, ['sales', 'expenses', 'ledger'], true) && $months !== [])
            <form method="GET" class="mt-4 flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label for="old-month" class="text-sm text-gray-600">Month</label>
                <select id="old-month" name="month" class="mbui-input !w-auto" onchange="this.form.submit()">
                    <option value="">Every month</option>
                    @foreach ($months as $m)
                        <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m)->format('F Y') }}</option>
                    @endforeach
                </select>
                <noscript><x-mbui.button type="submit" variant="secondary">Show</x-mbui.button></noscript>
            </form>
        @endif

        @if ($tab === 'summary')
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-mbui.card class="p-6">
                    <p class="text-sm font-medium text-gray-500">Capital</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">TZS {{ number_format($totals['capital']) }}</p>
                </x-mbui.card>
                <x-mbui.card class="p-6">
                    <p class="text-sm font-medium text-gray-500">Income</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600">TZS {{ number_format($totals['income']) }}</p>
                </x-mbui.card>
                <x-mbui.card class="p-6">
                    <p class="text-sm font-medium text-gray-500">Expenses</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-red-600">TZS {{ number_format($totals['expenses']) }}</p>
                </x-mbui.card>
                <x-mbui.card class="p-6">
                    <p class="text-sm font-medium text-gray-500">Net</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight {{ $totals['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($totals['balance']) }}</p>
                    @if ($totals['repaid'] > 0)
                        <p class="mt-1 text-xs text-gray-500">After TZS {{ number_format($totals['repaid']) }} in loan repayments</p>
                    @endif
                </x-mbui.card>
            </div>

            {{-- Each account: what the old records leave in it, against what was counted on the start day. --}}
            @if ($accounts->isNotEmpty())
                <div class="mt-8 mbui-card overflow-hidden">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-gray-900">Money accounts on {{ $start->copy()->subDay()->format('d M Y') }}</h2>
                        <p class="mt-1 text-sm text-gray-500">What these records leave in each account, and the money counted in it when Finance started. The difference is money the old records missed.</p>
                    </div>
                    <x-mbui.table>
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="mbui-th">Account</th>
                                <th class="mbui-th text-right">In</th>
                                <th class="mbui-th text-right">Out</th>
                                <th class="mbui-th text-right">Left by the records</th>
                                <th class="mbui-th text-right">Counted on {{ $start->format('d M') }}</th>
                                <th class="mbui-th text-right">Difference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($accounts as $b)
                                <tr>
                                    <td class="mbui-td">{{ $b['account']->name }}@unless ($b['account']->is_active) <span class="text-xs text-gray-400">· archived</span>@endunless</td>
                                    <td class="mbui-td text-right tabular-nums text-emerald-700">{{ number_format($b['in']) }}</td>
                                    <td class="mbui-td text-right tabular-nums text-red-600">{{ number_format($b['out']) }}</td>
                                    <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format($b['balance']) }}</td>
                                    <td class="mbui-td text-right tabular-nums">{{ $b['counted_at_start'] === null ? '—' : number_format($b['counted_at_start']) }}</td>
                                    <td class="mbui-td text-right tabular-nums">
                                        @if ($b['counted_at_start'] !== null)
                                            @php($diff = round($b['counted_at_start'] - $b['balance'], 2))
                                            <span class="{{ $diff == 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $diff == 0 ? 'Matches' : ($diff > 0 ? '+' : '').number_format($diff) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-mbui.table>
                    @if ($unassigned && ($unassigned['items'] > 0 || $unassigned['payments'] != 0))
                        <p class="border-t border-gray-200 px-6 py-3 text-sm text-amber-700">TZS {{ number_format($unassigned['net']) }} of these records was never tied to an account.</p>
                    @endif
                </div>
            @endif

            @if ($broughtForward->isNotEmpty())
                <div class="mt-8 mbui-card p-6">
                    <h2 class="text-base font-semibold text-gray-900">Loans still owed on {{ $start->format('d M Y') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Brought forward into the books: repay them from the Capital page.</p>
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        @foreach ($broughtForward as $l)
                            <li class="flex justify-between gap-3 py-1.5">
                                <a href="{{ route('admin.finance.capital.repayments.index', $l['loan']) }}" class="text-gray-700 hover:text-indigo-600">{{ $l['loan']->label }} · {{ $l['loan']->source }}</a>
                                <span class="font-semibold tabular-nums">TZS {{ number_format($l['outstanding']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-8 mbui-card overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Per software</h2>
                    <p class="mt-1 text-sm text-gray-500">Everything before {{ $start->format('d M Y') }}.</p>
                </div>
                <x-mbui.table>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="mbui-th">Software</th>
                            <th class="mbui-th text-right">Capital</th>
                            <th class="mbui-th text-right">Income</th>
                            <th class="mbui-th text-right">Expenses</th>
                            <th class="mbui-th text-right">Repaid</th>
                            <th class="mbui-th text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $r)
                            <tr>
                                <td class="mbui-td font-medium text-gray-900">{{ $r['label'] }}</td>
                                <td class="mbui-td text-right tabular-nums">{{ number_format($r['capital']) }}</td>
                                <td class="mbui-td text-right tabular-nums text-emerald-700">{{ number_format($r['income']) }}</td>
                                <td class="mbui-td text-right tabular-nums text-red-600">{{ number_format($r['expenses']) }}</td>
                                <td class="mbui-td text-right tabular-nums">{{ number_format($r['repaid']) }}</td>
                                <td class="mbui-td text-right font-semibold tabular-nums {{ $r['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">{{ number_format($r['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="mbui-td text-center text-gray-500">No records before {{ $start->format('d M Y') }}.</td></tr>
                        @endforelse
                    </tbody>
                </x-mbui.table>
            </div>

        @elseif ($tab === 'ledger')
            <div class="mt-6 mbui-card overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Trial balance</h2>
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
                        @forelse ($trial as $t)
                            <tr>
                                <td class="mbui-td whitespace-normal">{{ $t['account'] }}</td>
                                <td class="mbui-td text-right tabular-nums">{{ $t['dr'] ? number_format($t['dr']) : '' }}</td>
                                <td class="mbui-td text-right tabular-nums">{{ $t['cr'] ? number_format($t['cr']) : '' }}</td>
                                <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format(abs($t['balance'])) }} <span class="text-xs font-normal text-gray-500">{{ $t['balance'] >= 0 ? 'Dr' : 'Cr' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="mbui-td text-center text-gray-500">Nothing recorded.</td></tr>
                        @endforelse
                    </tbody>
                </x-mbui.table>
            </div>

            <div class="mt-6 mbui-card overflow-hidden">
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
                        @forelse ($lines as $l)
                            <tr>
                                <td class="mbui-td whitespace-nowrap text-gray-500">{{ $l['date']->format('d M Y') }}</td>
                                <td class="mbui-td whitespace-normal">
                                    <span class="font-medium text-gray-900">{{ $l['description'] }}</span>
                                    @if ($l['ref'])
                                        <p class="text-xs text-gray-500">{{ $l['ref'] }}</p>
                                    @endif
                                </td>
                                <td class="mbui-td whitespace-normal text-sm text-sky-800">{{ $l['dr'] }}</td>
                                <td class="mbui-td whitespace-normal text-sm text-orange-800">{{ $l['cr'] }}</td>
                                <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format($l['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="mbui-td text-center text-gray-500">Nothing recorded.</td></tr>
                        @endforelse
                    </tbody>
                </x-mbui.table>
            </div>

        @elseif ($records === null)
            <p class="mt-6 text-sm text-gray-500">Nothing of this kind was recorded yet.</p>

        @else
            <div class="mt-6 mbui-card overflow-hidden">
                <x-mbui.table>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="mbui-th">Date</th>
                            <th class="mbui-th">Description</th>
                            <th class="mbui-th">Details</th>
                            <th class="mbui-th text-right">Amount (TZS)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($records as $r)
                            <tr>
                                @switch($tab)
                                    @case('sales')
                                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ \App\Support\FinanceDates::payment($r)?->format('d M Y') }}</td>
                                        <td class="mbui-td whitespace-normal font-medium text-gray-900">{{ $r->order?->itemName() ?? 'Order' }} <span class="text-xs font-normal text-gray-500">{{ $r->order?->order_number }}</span></td>
                                        <td class="mbui-td whitespace-normal text-xs text-gray-500">{{ $r->user?->name }} · {{ $r->payment_method }} · {{ $r->transaction_reference }}</td>
                                        @break
                                    @case('expenses')
                                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ $r->spent_at?->format('d M Y') }}</td>
                                        <td class="mbui-td whitespace-normal font-medium text-gray-900">{{ $r->label }}</td>
                                        <td class="mbui-td whitespace-normal text-xs text-gray-500">{{ $r->typeLabel() ?? $r->category }}@if ($r->product || $r->tool) · {{ $r->product->name ?? $r->tool->name }}@endif @if ($r->description) · {{ $r->description }}@endif</td>
                                        @break
                                    @case('capital')
                                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ \App\Support\FinanceDates::capital($r)?->format('d M Y') }}</td>
                                        <td class="mbui-td whitespace-normal font-medium text-gray-900">{{ $r->label }}</td>
                                        <td class="mbui-td whitespace-normal text-xs text-gray-500">{{ $r->is_loan ? 'Loan' : 'Capital' }} · {{ $r->source }}@if ($r->is_loan) · repaid TZS {{ number_format($r->repaidAmount()) }}@endif</td>
                                        @break
                                    @case('repayments')
                                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ $r->paid_at?->format('d M Y') }}</td>
                                        <td class="mbui-td whitespace-normal font-medium text-gray-900">Loan repaid: {{ $r->capitalEntry?->label ?? 'loan' }}</td>
                                        <td class="mbui-td whitespace-normal text-xs text-gray-500">{{ $r->capitalEntry?->source }} @if ($r->reference) · {{ $r->reference }}@endif</td>
                                        @break
                                    @case('transfers')
                                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ $r->transferred_at?->format('d M Y') }}</td>
                                        <td class="mbui-td whitespace-normal font-medium text-gray-900">{{ $r->fromAccount?->name }} → {{ $r->toAccount?->name }}</td>
                                        <td class="mbui-td whitespace-normal text-xs text-gray-500">{{ $r->reference }} {{ $r->notes }}</td>
                                        @break
                                    @case('given')
                                        <td class="mbui-td whitespace-nowrap text-gray-500">{{ $r->allocated_at?->format('d M Y') }}</td>
                                        <td class="mbui-td whitespace-normal font-medium text-gray-900">{{ $r->label }}</td>
                                        <td class="mbui-td whitespace-normal text-xs text-gray-500">From {{ $r->moneyAccount?->name }} @if ($r->notes) · {{ $r->notes }}@endif</td>
                                        @break
                                @endswitch
                                <td class="mbui-td text-right font-semibold tabular-nums">{{ number_format($r->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="mbui-td text-center text-gray-500">Nothing here before {{ $start->format('d M Y') }}.</td></tr>
                        @endforelse
                    </tbody>
                </x-mbui.table>
            </div>
            <div class="mt-4">{{ $records->links() }}</div>
        @endif
    @endif

</x-layouts.finance>
