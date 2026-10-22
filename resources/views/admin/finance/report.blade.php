<x-layouts.finance title="Monthly report" header="Finance">

    @php
        $label = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $pnl['month'])->format('F Y');
        $prevLabel = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $previous['month'])->format('M Y');
        $money = fn ($v) => ($v < 0 ? '−' : '').number_format(abs($v));
        $doneCount = collect($checklist)->where('done', true)->count();
        $checkable = collect($checklist)->whereNotNull('done')->count();
    @endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Monthly report · {{ $label }}</h1>
            <p class="mt-1 text-sm text-gray-500">Profit and loss, profit per product, and the month-end checklist.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <label for="report-month" class="text-sm text-gray-600">Month</label>
                <select id="report-month" name="month" class="mbui-input" onchange="this.form.submit()">
                    @foreach ($months as $m)
                        <option value="{{ $m }}" @selected($pnl['month'] === $m)>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m)->format('F Y') }}</option>
                    @endforeach
                </select>
                <noscript><x-mbui.button type="submit" variant="secondary">Show</x-mbui.button></noscript>
            </form>
            <a href="{{ route('admin.finance.report.csv', ['month' => $pnl['month']]) }}" class="inline-flex items-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Download CSV</a>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Income', $pnl['income_total'], $previous['income_total'], 'text-emerald-600'], ['Cost of sales', $pnl['cogs_total'], $previous['cogs_total'], 'text-gray-900'], ['Gross profit', $pnl['gross'], $previous['gross'], 'text-gray-900'], ['Net profit', $pnl['net'], $previous['net'], $pnl['net'] >= 0 ? 'text-emerald-700' : 'text-red-600']] as [$title, $now, $before, $class])
            <x-mbui.card class="p-6">
                <p class="text-sm font-medium text-gray-500">{{ $title }}</p>
                <p class="mt-2 text-2xl font-bold tracking-tight {{ $class }}">TZS {{ $money($now) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $prevLabel }}: TZS {{ $money($before) }}</p>
            </x-mbui.card>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Profit and loss statement --}}
        <div class="mbui-card overflow-hidden lg:col-span-2">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-900">Profit and loss</h2>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr class="bg-gray-50"><td class="px-6 py-2 font-semibold text-gray-700" colspan="2">Income</td></tr>
                    @forelse ($pnl['income'] as $name => $v)
                        <tr><td class="px-6 py-1.5 pl-10 text-gray-700">{{ $name }}</td><td class="px-6 py-1.5 text-right tabular-nums">{{ number_format($v) }}</td></tr>
                    @empty
                        <tr><td class="px-6 py-1.5 pl-10 text-gray-400" colspan="2">No sales this month</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td class="px-6 py-2">Total income</td><td class="px-6 py-2 text-right tabular-nums">{{ number_format($pnl['income_total']) }}</td></tr>

                    <tr class="bg-gray-50"><td class="px-6 py-2 font-semibold text-gray-700" colspan="2">Cost of sales (product accounts, COGS)</td></tr>
                    @forelse ($pnl['cogs'] as $name => $v)
                        <tr><td class="px-6 py-1.5 pl-10 text-gray-700">{{ $name }}</td><td class="px-6 py-1.5 text-right tabular-nums">({{ number_format($v) }})</td></tr>
                    @empty
                        <tr><td class="px-6 py-1.5 pl-10 text-gray-400" colspan="2">None recorded. Product account purchases belong here (type "Buying / renewing product accounts").</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td class="px-6 py-2">Gross profit</td><td class="px-6 py-2 text-right tabular-nums">{{ $money($pnl['gross']) }}</td></tr>

                    <tr class="bg-gray-50"><td class="px-6 py-2 font-semibold text-gray-700" colspan="2">Operating expenses</td></tr>
                    @forelse ($pnl['opex'] as $name => $v)
                        <tr><td class="px-6 py-1.5 pl-10 text-gray-700">{{ $name }}</td><td class="px-6 py-1.5 text-right tabular-nums">({{ number_format($v) }})</td></tr>
                    @empty
                        <tr><td class="px-6 py-1.5 pl-10 text-gray-400" colspan="2">None this month</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td class="px-6 py-2">Total operating expenses</td><td class="px-6 py-2 text-right tabular-nums">({{ number_format($pnl['opex_total']) }})</td></tr>

                    <tr class="border-t-2 border-gray-300 text-base font-bold {{ $pnl['net'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                        <td class="px-6 py-3">Net profit</td><td class="px-6 py-3 text-right tabular-nums">{{ $money($pnl['net']) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-gray-200 bg-gray-50 px-6 py-3 text-xs text-gray-600">
                Cash that moved outside profit this month: owner capital in TZS {{ number_format($pnl['cash']['capital_in']) }} ·
                loans received TZS {{ number_format($pnl['cash']['loans_in']) }} · loans repaid TZS {{ number_format($pnl['cash']['repaid']) }}.
            </div>
        </div>

        {{-- Month-end checklist --}}
        <div class="mbui-card p-6 self-start">
            <h2 class="text-base font-semibold text-gray-900">Month-end checklist</h2>
            <p class="mt-1 text-sm text-gray-500">{{ $doneCount }} of {{ $checkable }} done</p>
            <ul class="mt-4 space-y-3">
                @foreach ($checklist as $c)
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $c['done'] === true ? 'bg-emerald-100 text-emerald-700' : ($c['done'] === false ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500') }}" aria-hidden="true">{{ $c['done'] === true ? '✓' : ($c['done'] === false ? '!' : '·') }}</span>
                        <div class="min-w-0 text-sm">
                            <a href="{{ $c['route'] }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $c['label'] }}</a>
                            <p class="text-xs {{ $c['done'] === false ? 'text-amber-700' : 'text-gray-500' }}">{{ $c['detail'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Profit per product --}}
    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Profit per product</h2>
            <p class="mt-1 text-sm text-gray-500">Sales minus the cost of the accounts bought for that product this month.</p>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Product</th>
                    <th class="mbui-th text-right">Sales</th>
                    <th class="mbui-th text-right">Cost</th>
                    <th class="mbui-th text-right">Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($pnl['products'] as $name => $p)
                    <tr>
                        <td class="mbui-td">{{ $name }}</td>
                        <td class="mbui-td text-right tabular-nums">{{ number_format($p['income']) }}</td>
                        <td class="mbui-td text-right tabular-nums">{{ number_format($p['cogs']) }}</td>
                        <td class="mbui-td text-right font-semibold tabular-nums {{ $p['profit'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">{{ $money($p['profit']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-500">No sales or product costs this month.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.finance>
