<x-layouts.finance title="Overview" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Finance Overview</h1>
            <p class="mt-1 text-sm text-gray-500">Capital, income, expenses, loan repayments and balance for each software.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.capital.index') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Add capital</a>
            <a href="{{ route('admin.finance.expenses.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add expense</a>
        </div>
    </div>

    {{-- Period: day, week, month, year (‹ › to step), all time, or a custom range. --}}
    @php($base = route('admin.finance.dashboard'))
    <div class="mt-6 flex flex-col gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex flex-wrap gap-1" aria-label="Period">
            @foreach (['day' => 'Today', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'all' => 'All time'] as $key => $text)
                <a href="{{ $base.($key === 'all' ? '' : '?period='.$key) }}"
                    @if ($period->key === $key) aria-current="page" @endif
                    class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $period->key === $key ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">{{ $text }}</a>
            @endforeach
        </nav>

        <div class="flex flex-wrap items-center gap-2">
            @if ($period->steps())
                <a href="{{ $base.'?'.http_build_query($period->shifted(-1)) }}" class="rounded-lg p-1.5 text-gray-500 ring-1 ring-gray-200 hover:bg-gray-50 hover:text-gray-900" aria-label="Previous {{ $period->key }}" title="Previous">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </a>
            @endif
            <span class="min-w-[9rem] text-center text-sm font-semibold text-gray-900">{{ $period->label() }}</span>
            @if ($period->steps())
                @if ($period->isCurrent())
                    <span class="rounded-lg p-1.5 text-gray-300 ring-1 ring-gray-100" aria-hidden="true">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </span>
                @else
                    <a href="{{ $base.'?'.http_build_query($period->shifted(1)) }}" class="rounded-lg p-1.5 text-gray-500 ring-1 ring-gray-200 hover:bg-gray-50 hover:text-gray-900" aria-label="Next {{ $period->key }}" title="Next">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                @endif
            @endif

            <form method="GET" action="{{ $base }}" class="flex flex-wrap items-center gap-1.5">
                <input type="hidden" name="period" value="custom">
                <label class="sr-only" for="period-from">From</label>
                <input id="period-from" type="date" name="from" value="{{ $period->key === 'custom' ? $period->from?->toDateString() : '' }}" class="mbui-input !w-auto py-1 text-sm">
                <span class="text-sm text-gray-400">–</span>
                <label class="sr-only" for="period-to">To</label>
                <input id="period-to" type="date" name="to" value="{{ $period->key === 'custom' ? $period->to?->toDateString() : '' }}" class="mbui-input !w-auto py-1 text-sm">
                <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 {{ $period->key === 'custom' ? 'bg-indigo-600 text-white ring-indigo-600' : 'text-gray-700 ring-gray-200 hover:bg-gray-50' }}">Show</button>
            </form>
        </div>
    </div>

    <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">{{ $period->isAll() ? 'Total Capital' : 'Capital' }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">TZS {{ number_format($totals['capital']) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">{{ $period->isAll() ? 'Total Income' : 'Income' }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600">TZS {{ number_format($totals['income']) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">{{ $period->isAll() ? 'Total Expenses' : 'Expenses' }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-red-600">TZS {{ number_format($totals['expenses']) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">{{ $period->isAll() ? 'Balance' : 'Net for the period' }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight {{ $totals['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($totals['balance']) }}</p>
            @if ($totals['repaid'] > 0)
                <p class="mt-1 text-xs text-gray-500">After TZS {{ number_format($totals['repaid']) }} in loan repayments</p>
            @endif
        </x-mbui.card>
    </div>

    {{-- Where the money is: one line per money account. --}}
    <div class="mt-8 mbui-card p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-base font-semibold text-gray-900">Where the money is <span class="text-sm font-normal text-gray-500">· now</span></h2>
            <a href="{{ route('admin.finance.money.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Money accounts &rarr;</a>
        </div>
        @if ($money === null || $money['accounts']->isEmpty())
            <p class="mt-2 text-sm text-gray-500">Set up money accounts (Alipay, WeChat, mobile money, bank, petty cash) to see how much sits in each.</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @foreach ($money['accounts'] as $b)
                    <li class="flex justify-between gap-3 py-1.5">
                        <a href="{{ route('admin.finance.money.show', $b['account']) }}" class="text-gray-700 hover:text-indigo-600">{{ $b['account']->name }}</a>
                        <span class="font-semibold tabular-nums {{ $b['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($b['balance']) }}</span>
                    </li>
                @endforeach
                @if ($money['unassigned'] != 0)
                    <li class="flex justify-between gap-3 py-1.5 text-amber-700">
                        <a href="{{ route('admin.finance.money.index') }}" class="hover:underline">Not assigned to an account</a>
                        <span class="font-semibold tabular-nums">TZS {{ number_format($money['unassigned']) }}</span>
                    </li>
                @endif
                <li class="flex justify-between gap-3 pt-2 font-semibold text-gray-900">
                    <span>In accounts</span>
                    <span class="tabular-nums">TZS {{ number_format($money['total']) }}</span>
                </li>
            </ul>
        @endif
    </div>

    <div class="mt-8 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Per software</h2>
            <p class="mt-1 text-sm text-gray-500">{{ $period->label() }}. Income is pulled automatically from approved payments. Capital and expenses are recorded manually. Click a name to see, edit or remove its records.</p>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Software</th>
                    <th class="mbui-th">Type</th>
                    <th class="mbui-th">Capital</th>
                    <th class="mbui-th">Income</th>
                    <th class="mbui-th">Expenses</th>
                    <th class="mbui-th">Loans repaid</th>
                    <th class="mbui-th">{{ $period->isAll() ? 'Balance' : 'Net' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($rows as $row)
                    <tr>
                        <td class="mbui-td font-medium"><a href="{{ route('admin.finance.line', ['line' => $row['key']] + $period->query()) }}" class="text-indigo-600 hover:text-indigo-500 hover:underline">{{ $row['label'] }}</a></td>
                        <td class="mbui-td"><x-mbui.badge appearance="neutral">{{ $row['type'] }}</x-mbui.badge></td>
                        <td class="mbui-td">TZS {{ number_format($row['capital']) }}</td>
                        <td class="mbui-td text-emerald-600">TZS {{ number_format($row['income']) }}</td>
                        <td class="mbui-td text-red-600">TZS {{ number_format($row['expenses']) }}</td>
                        <td class="mbui-td text-amber-600">TZS {{ number_format($row['repaid']) }}</td>
                        <td class="mbui-td font-semibold {{ $row['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($row['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">{{ $period->isAll() ? 'No software tracked yet' : 'Nothing recorded in this period' }}</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.finance>
