@php
    $back = request()->fullUrl();
    $overview = route('admin.finance.dashboard', $period->query());
    $date = fn ($d) => $d?->format('d M Y') ?? '—';
@endphp

<x-layouts.finance :title="$row['label']" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $row['label'] }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $row['type'] }} · {{ $period->label() }}. The records behind each number on the overview: edit or remove them here.</p>
        </div>
        <a href="{{ $overview }}" class="mbui-anchor text-sm">Back to overview</a>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Capital', $row['capital'], 'text-gray-900'],
            ['Income', $row['income'], 'text-emerald-600'],
            ['Expenses', $row['expenses'], 'text-red-600'],
            ['Loans repaid', $row['repaid'], 'text-amber-600'],
            [$period->isAll() ? 'Balance' : 'Net', $row['balance'], $row['balance'] >= 0 ? 'text-gray-900' : 'text-red-600'],
        ] as [$name, $amount, $tone])
            <x-mbui.card class="p-5">
                <p class="text-sm font-medium text-gray-500">{{ $name }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight {{ $tone }}">TZS {{ number_format($amount) }}</p>
            </x-mbui.card>
        @endforeach
    </div>

    {{-- Capital and loans --}}
    <div class="mt-8 mbui-card overflow-hidden">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Capital · {{ $capital->count() }}</h2>
            <a href="{{ route('admin.finance.capital.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Add capital &rarr;</a>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th">Date received</th>
                    <th class="mbui-th">Source</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($capital as $entry)
                    <tr>
                        <td class="mbui-td text-gray-500">{{ $date(\App\Support\FinanceDates::capital($entry)) }}</td>
                        <td class="mbui-td">
                            <p class="font-medium text-gray-900">{{ $entry->source }}</p>
                            <p class="text-xs text-gray-500">{{ $entry->label }}@if ($entry->is_loan) · Loan @endif</p>
                        </td>
                        <td class="mbui-td font-semibold text-gray-900">TZS {{ number_format($entry->amount) }}</td>
                        <td class="mbui-td">
                            <div class="flex items-center justify-end gap-3">
                                @if ($entry->is_loan)
                                    <a href="{{ route('admin.finance.capital.repayments.index', $entry) }}" class="mbui-anchor text-sm">Repayments</a>
                                @endif
                                <x-mbui.icon-link :href="route('admin.finance.capital.edit', ['capitalEntry' => $entry, 'back' => $back])" />
                                <x-mbui.reasoned-action :action="route('admin.finance.capital.destroy', $entry)" method="DELETE" label="Remove" prompt-text="Why are you removing this capital entry?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No capital in this period</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    {{-- Expenses --}}
    <div class="mt-8 mbui-card overflow-hidden">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Expenses · {{ $expenses->count() }}</h2>
            <a href="{{ route('admin.finance.expenses.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Add expense &rarr;</a>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th">Date</th>
                    <th class="mbui-th">Expense</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="mbui-td text-gray-500">{{ $date($expense->spent_at) }}</td>
                        <td class="mbui-td">
                            <p class="font-medium text-gray-900">{{ $expense->label }}</p>
                            <p class="text-xs text-gray-500">{{ collect([$expense->typeLabel(), $expense->description, $expense->isAutomatic() ? 'Recorded automatically' : null])->filter()->implode(' · ') }}</p>
                        </td>
                        <td class="mbui-td font-semibold text-red-600">TZS {{ number_format($expense->amount) }}</td>
                        <td class="mbui-td">
                            <div class="flex items-center justify-end gap-3">
                                <x-mbui.icon-link :href="route('admin.finance.expenses.edit', ['expense' => $expense, 'back' => $back])" />
                                <x-mbui.reasoned-action :action="route('admin.finance.expenses.destroy', $expense)" method="DELETE" label="Remove" prompt-text="Why are you removing this expense?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No expenses in this period</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    {{-- Loan repayments --}}
    @if ($repaid->isNotEmpty())
        <div class="mt-8 mbui-card overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-900">Loans repaid · {{ $repaid->count() }}</h2>
                <p class="mt-1 text-sm text-gray-500">Change or remove a repayment on its loan's Repayments page.</p>
            </div>
            <x-mbui.table>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Date paid</th>
                        <th class="mbui-th">Loan</th>
                        <th class="mbui-th">Amount</th>
                        <th class="mbui-th"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($repaid as $repayment)
                        <tr>
                            <td class="mbui-td text-gray-500">{{ $date($repayment->paid_at) }}</td>
                            <td class="mbui-td text-gray-900">{{ $repayment->capitalEntry->label }} · {{ $repayment->capitalEntry->source }}</td>
                            <td class="mbui-td font-semibold text-amber-600">TZS {{ number_format($repayment->amount) }}</td>
                            <td class="mbui-td text-right">
                                <a href="{{ route('admin.finance.capital.repayments.index', $repayment->capitalEntry) }}" class="mbui-anchor text-sm">Repayments</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    @endif

    {{-- Income: approved customer payments --}}
    <div class="mt-8 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Income · {{ $income->count() }}</h2>
            <p class="mt-1 text-sm text-gray-500">Approved customer payments, counted on the day the customer paid. They come from Payments and are changed there, not in Finance.</p>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th">Date paid</th>
                    <th class="mbui-th">Order</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($income as $payment)
                    <tr>
                        <td class="mbui-td text-gray-500">{{ $date(\App\Support\FinanceDates::payment($payment)) }}</td>
                        <td class="mbui-td">
                            <p class="font-medium text-gray-900">{{ $payment->order->order_number }}</p>
                            <p class="text-xs text-gray-500">{{ $payment->user?->name }} · {{ $payment->payment_method }}</p>
                        </td>
                        <td class="mbui-td font-semibold text-emerald-600">TZS {{ number_format($payment->amount) }}</td>
                        <td class="mbui-td text-right">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="mbui-anchor text-sm">Open payment</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="mbui-td text-center text-gray-400">No income in this period</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.finance>
