<x-layouts.finance title="Budget" header="Finance">

    @php($label = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $report['month'])->format('F Y'))

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Budget · {{ $label }}</h1>
            <p class="mt-1 text-sm text-gray-500">Each month's income is split by percentage before it is spent. Spending counts against a bucket by its expense type.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label for="budget-month" class="text-sm text-gray-600">Month</label>
            <select id="budget-month" name="month" class="mbui-input" onchange="this.form.submit()">
                @foreach ($months as $m)
                    <option value="{{ $m }}" @selected($report['month'] === $m)>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m)->format('F Y') }}</option>
                @endforeach
            </select>
            <noscript><x-mbui.button type="submit" variant="secondary">Show</x-mbui.button></noscript>
        </form>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-3">
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Income this month</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600">TZS {{ number_format($report['income']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Approved customer payments</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Spent this month</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-red-600">TZS {{ number_format($report['spent']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Expenses and loan repayments</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Left over</p>
            <p class="mt-2 text-2xl font-bold tracking-tight {{ $report['income'] - $report['spent'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($report['income'] - $report['spent']) }}</p>
        </x-mbui.card>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Bucket</th>
                    <th class="mbui-th text-right">Share</th>
                    <th class="mbui-th text-right">Budget</th>
                    <th class="mbui-th text-right">Spent</th>
                    <th class="mbui-th">Used</th>
                    <th class="mbui-th text-right">Left</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($report['rows'] as $r)
                    @php($over = $r['left'] < 0)
                    <tr>
                        <td class="mbui-td whitespace-normal">
                            <p class="font-medium text-gray-900">{{ $r['label'] }}</p>
                            <p class="text-xs text-gray-500">{{ $r['note'] }}</p>
                        </td>
                        <td class="mbui-td text-right tabular-nums">{{ rtrim(rtrim(number_format($r['percent'], 2), '0'), '.') }}%</td>
                        <td class="mbui-td text-right tabular-nums">{{ number_format($r['budget']) }}</td>
                        <td class="mbui-td text-right tabular-nums">{{ number_format($r['spent']) }}</td>
                        <td class="mbui-td w-40">
                            @php($pct = $r['used_pct'] ?? 100)
                            <div class="h-2 w-full rounded-full bg-gray-100" role="img" aria-label="{{ $r['used_pct'] === null ? 'Spent with no budget' : $pct.'% used' }}">
                                <div class="h-2 rounded-full {{ $over ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                            </div>
                            <p class="mt-0.5 text-xs {{ $over ? 'font-semibold text-red-600' : 'text-gray-500' }}">{{ $r['used_pct'] === null ? 'No budget' : $pct.'%' }}</p>
                        </td>
                        <td class="mbui-td text-right font-semibold tabular-nums {{ $over ? 'text-red-600' : 'text-gray-900' }}">{{ $over ? '−'.number_format(-$r['left']) : number_format($r['left']) }}</td>
                    </tr>
                @endforeach
                @if ($report['unbudgeted'] > 0)
                    <tr class="bg-amber-50/60">
                        <td class="mbui-td whitespace-normal" colspan="3">
                            <p class="font-medium text-amber-800">Not in any bucket</p>
                            <p class="text-xs text-amber-700">Expenses with no type and no category. Give them a type under Expenses.</p>
                        </td>
                        <td class="mbui-td text-right tabular-nums text-amber-800">{{ number_format($report['unbudgeted']) }}</td>
                        <td class="mbui-td" colspan="2"></td>
                    </tr>
                @endif
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="mbui-card p-6">
            <h2 class="text-base font-semibold text-gray-900">Percentages</h2>
            <p class="mt-1 text-sm text-gray-500">Agree these with the owners. They must add up to 100%.</p>
            <form method="POST" action="{{ route('admin.finance.budget.update') }}" class="mt-4 space-y-3">
                @csrf
                @method('PUT')
                @foreach ($buckets as $key => $b)
                    <div class="flex items-center justify-between gap-3">
                        <label for="pct-{{ $key }}" class="text-sm text-gray-700">{{ $b['label'] }}</label>
                        <div class="flex items-center gap-1">
                            <input id="pct-{{ $key }}" type="number" step="0.5" min="0" max="100" name="percents[{{ $key }}]" value="{{ old('percents.'.$key, $b['percent']) }}" class="mbui-input w-24 text-right" data-budget-pct>
                            <span class="text-sm text-gray-500">%</span>
                        </div>
                    </div>
                @endforeach
                <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                    <p class="text-sm" id="pct-total" aria-live="polite"></p>
                    <x-mbui.button type="submit">Save percentages</x-mbui.button>
                </div>
            </form>
        </div>

        <div class="mbui-card p-6 self-start">
            <h2 class="text-base font-semibold text-gray-900">Capital by product</h2>
            <p class="mt-1 text-sm text-gray-500">How the capital and loans recorded so far are spread.</p>
            @if (empty($capital))
                <p class="mt-4 text-sm text-gray-500">No capital recorded yet.</p>
            @else
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($capital as $c)
                        <li>
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-700">{{ $c['label'] }}</span>
                                <span class="tabular-nums text-gray-900">TZS {{ number_format($c['amount']) }} <span class="text-xs text-gray-500">· {{ $c['share'] }}%</span></span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ $c['share'] }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var inputs = document.querySelectorAll('[data-budget-pct]');
            var out = document.getElementById('pct-total');
            var update = function () {
                var sum = 0;
                inputs.forEach(function (i) { sum += parseFloat(i.value || '0'); });
                sum = Math.round(sum * 100) / 100;
                out.textContent = 'Total ' + sum + '%';
                out.className = 'text-sm font-semibold ' + (sum === 100 ? 'text-emerald-700' : 'text-red-600');
            };
            inputs.forEach(function (i) { i.addEventListener('input', update); });
            update();
        })();
    </script>

</x-layouts.finance>
