@php
    $back = request()->fullUrl();
    $overview = route('admin.finance.dashboard', $period->query());
    $date = fn ($d) => $d?->format('d M Y') ?? '—';
    [$kind, $id] = array_pad(explode(':', $row['key'], 2), 2, null);
    $types = \App\Models\FinanceExpense::typesReady() ? \App\Models\FinanceExpense::types() : [];
    $today = \App\Support\FinanceDates::today();
@endphp

<x-layouts.finance :title="$row['label']" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $row['label'] }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $row['type'] }} · {{ $period->label() }}. The records behind each number on the overview: edit or remove them here.</p>
        </div>
        <a href="{{ $overview }}" class="mbui-anchor text-sm">Back to overview</a>
    </div>

    @if ($kind === 'pool')
    {{-- Company money lent to lines: not a loss, so no minus signs here. --}}
    @php($givenTotal = (float) $given->sum('amount'))
    <div class="mt-6 grid gap-5 sm:grid-cols-3">
        @foreach ([
            ['Given to lines', $givenTotal, 'text-gray-900', 'Company money lent to lines for their costs'],
            ['Spent', max(0, $givenTotal - $notSpent), 'text-red-600', 'Paid out of the account it was given from'],
            ['Not spent yet', $notSpent, 'text-emerald-600', 'Still in the account, kept for those lines'],
        ] as [$name, $amount, $tone, $help])
            <x-mbui.card class="p-5">
                <p class="text-sm font-medium text-gray-500">{{ $name }}</p>
                <p class="mt-1 text-xl font-bold tracking-tight {{ $tone }}">TZS {{ number_format($amount) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
            </x-mbui.card>
        @endforeach
    </div>
    @else
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
    @endif

    @if ($kind !== 'pool')
    {{-- Capital and loans --}}
    <div class="mt-8 mbui-card overflow-hidden" x-data="{ adding: {{ $errors->any() && old('form') === 'capital' ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Capital · {{ $capital->count() }}</h2>
            <button type="button" x-on:click="adding = ! adding" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" x-text="adding ? 'Cancel' : '+ Add capital here'">+ Add capital here</button>
        </div>

        {{-- Capital added here lands on this line (same software, or this name). --}}
        <form method="POST" action="{{ route('admin.finance.capital.store') }}" x-show="adding" x-cloak class="space-y-4 border-b border-gray-200 bg-gray-50 px-6 py-5">
            @csrf
            <input type="hidden" name="form" value="capital">
            <input type="hidden" name="label" value="{{ $row['label'] }}">
            @if ($kind === 'product')<input type="hidden" name="product_id" value="{{ $id }}">@endif
            @if ($kind === 'tool')<input type="hidden" name="tool_id" value="{{ $id }}">@endif
            <p class="text-sm text-gray-600">For <span class="font-semibold text-gray-900">{{ $row['label'] }}</span></p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="line-cap-amount" value="Amount (TZS)" />
                    <x-text-input id="line-cap-amount" class="mbui-input mt-1" type="number" step="0.01" min="0.01" name="amount" :value="old('form') === 'capital' ? old('amount') : ''" required />
                    <x-input-error :messages="old('form') === 'capital' ? $errors->get('amount') : []" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="line-cap-source" value="Source of this money" />
                    <x-text-input id="line-cap-source" class="mbui-input mt-1" type="text" name="source" :value="old('source')" placeholder="e.g. Owner savings, CRDB loan" required />
                    <x-input-error :messages="$errors->get('source')" class="mt-2" />
                </div>
                <x-finance.money-account-select :accounts="$moneyAccounts" id="line-cap-money" label="Received into" :suggest="true" />
                @if (\App\Support\FinanceDates::hasReceivedDate())
                    <div>
                        <x-input-label for="line-cap-received" value="Date received" />
                        <x-text-input id="line-cap-received" class="mbui-input mt-1" type="date" name="received_at" :value="old('received_at', \App\Support\FinanceDates::today())" :max="\App\Support\FinanceDates::today()" required />
                        <x-input-error :messages="$errors->get('received_at')" class="mt-2" />
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_loan" value="0">
                    <input type="checkbox" name="is_loan" value="1" @checked(old('is_loan')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    This is a loan and needs to be repaid
                </label>
                <x-mbui.button type="submit">Save capital</x-mbui.button>
            </div>
        </form>
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
    @endif

    {{-- Given from available money: money already in an account, now this line's capital. --}}
    @if ($allocationsReady)
        <div class="mt-8 mbui-card overflow-hidden" x-data="{ adding: {{ $errors->any() && old('form') === 'give' ? 'true' : 'false' }} }">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-6 py-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ $kind === 'pool' ? 'Given to lines' : 'Given from available money' }} · {{ $given->count() }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Money already in an account, given to {{ $kind === 'pool' ? 'a line' : 'this line' }} as capital. It leaves the account once, when the line spends it (an expense paid from that account), and adds nothing to the company's total.</p>
                </div>
                @if ($kind !== 'pool' && $available->isNotEmpty())
                    <button type="button" x-on:click="adding = ! adding" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" x-text="adding ? 'Cancel' : '+ Give from available money'">+ Give from available money</button>
                @endif
            </div>

            @if ($kind !== 'pool' && $available->isNotEmpty())
                <form method="POST" action="{{ route('admin.finance.allocations.store') }}" x-show="adding" x-cloak class="space-y-4 border-b border-gray-200 bg-gray-50 px-6 py-5">
                    @csrf
                    <input type="hidden" name="form" value="give">
                    <input type="hidden" name="label" value="{{ $row['label'] }}">
                    @if ($kind === 'product')<input type="hidden" name="product_id" value="{{ $id }}">@endif
                    @if ($kind === 'tool')<input type="hidden" name="tool_id" value="{{ $id }}">@endif
                    <p class="text-sm text-gray-600">To <span class="font-semibold text-gray-900">{{ $row['label'] }}</span></p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="give-account" value="From account" />
                            <select id="give-account" name="money_account_id" class="mbui-input mt-1" required>
                                @foreach ($available as $b)
                                    <option value="{{ $b['account']->id }}" @selected((string) old('money_account_id') === (string) $b['account']->id)>{{ $b['account']->name }} · TZS {{ number_format(max(0, $b['balance'])) }} left to give{{ $b['in'] != $b['balance'] ? ' (TZS '.number_format($b['in']).' in it, TZS '.number_format($b['in'] - $b['balance']).' given and not spent yet)' : '' }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('money_account_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="give-amount" value="Amount (TZS)" />
                            <x-text-input id="give-amount" class="mbui-input mt-1" type="number" step="0.01" min="0.01" name="amount" :value="old('form') === 'give' ? old('amount') : ''" required />
                            <x-input-error :messages="old('form') === 'give' ? $errors->get('amount') : []" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="give-date" value="Date" />
                            <x-text-input id="give-date" class="mbui-input mt-1" type="date" name="allocated_at" :value="old('allocated_at', $today)" :max="$today" required />
                            <x-input-error :messages="$errors->get('allocated_at')" class="mt-2" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <x-input-label for="give-notes" value="Notes (optional)" />
                            <x-text-input id="give-notes" class="mbui-input mt-1" type="text" name="notes" :value="old('notes')" placeholder="e.g. For this month's bundles" />
                        </div>
                        <x-mbui.button type="submit">Give</x-mbui.button>
                    </div>
                </form>
            @endif

            {{-- Given for expenses: the money leaves the account when the line spends it. --}}
            @foreach ($spentElsewhere as $s)
                @php($name = $s['account']->name)
                <form method="POST" action="{{ route('admin.finance.allocations.pay-from') }}" class="border-b border-amber-200 bg-amber-50 px-6 py-4 text-sm">
                    @csrf
                    <input type="hidden" name="money_account_id" value="{{ $s['account']->id }}">
                    <p class="font-semibold text-amber-900">TZS {{ number_format($s['given']) }} given from {{ $name }}, but only TZS {{ number_format($s['paid']) }} of this line's expenses is paid from {{ $name }}.</p>
                    <p class="mt-0.5 text-amber-800">Money given for expenses leaves {{ $name }} when it is spent. If {{ $name }} paid these, tick them: they will be paid from {{ $name }} and its balance goes down.</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($s['expenses'] as $e)
                            <li>
                                <label class="flex items-center gap-2 text-gray-800">
                                    <input type="checkbox" name="expense_ids[]" value="{{ $e->id }}" @checked(in_array($e->id, $s['tick'], true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>{{ $date($e->spent_at) }} · {{ $e->label }} · <span class="font-semibold">TZS {{ number_format($e->amount) }}</span> · now from {{ $e->moneyAccount?->name ?? 'no account' }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <x-input-error :messages="$errors->get('expense_ids')" class="mt-2" />
                    <div class="mt-3 flex justify-end">
                        <x-mbui.button type="submit">Pay ticked from {{ $name }}</x-mbui.button>
                    </div>
                </form>
            @endforeach

            <x-mbui.table>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Date</th>
                        <th class="mbui-th">{{ $kind === 'pool' ? 'To' : 'From account' }}</th>
                        <th class="mbui-th">Amount</th>
                        <th class="mbui-th"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($given as $g)
                        <tr>
                            <td class="mbui-td text-gray-500">{{ $date($g->allocated_at) }}</td>
                            <td class="mbui-td">
                                <p class="font-medium text-gray-900">{{ $kind === 'pool' ? $g->label : ($g->moneyAccount?->name ?? 'Account') }}</p>
                                <p class="text-xs text-gray-500">{{ collect([$kind === 'pool' ? 'From '.($g->moneyAccount?->name ?? 'account') : null, $g->notes])->filter()->implode(' · ') }}</p>
                            </td>
                            <td class="mbui-td font-semibold text-gray-900">TZS {{ number_format($g->amount) }}</td>
                            <td class="mbui-td">
                                <div class="flex items-center justify-end">
                                    <x-mbui.reasoned-action :action="route('admin.finance.allocations.destroy', $g)" method="DELETE" label="Remove" prompt-text="Why are you removing this? The money becomes available again." />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="mbui-td text-center text-gray-400">Nothing given in this period</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>
        </div>
    @endif

    @if ($kind !== 'pool')
    {{-- Expenses --}}
    <div class="mt-8 mbui-card overflow-hidden" x-data="{ adding: {{ $errors->any() && old('form') === 'expense' ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Expenses · {{ $expenses->count() }}</h2>
            <button type="button" x-on:click="adding = ! adding" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500" x-text="adding ? 'Cancel' : '+ Add expense here'">+ Add expense here</button>
        </div>

        {{-- An expense added here lands on this line: the same software, this
             cost type, or this name. --}}
        <form method="POST" action="{{ route('admin.finance.expenses.store') }}" enctype="multipart/form-data" x-show="adding" x-cloak class="space-y-4 border-b border-gray-200 bg-gray-50 px-6 py-5">
            @csrf
            <input type="hidden" name="form" value="expense">
            @if ($kind === 'product')<input type="hidden" name="product_id" value="{{ $id }}">@endif
            @if ($kind === 'tool')<input type="hidden" name="tool_id" value="{{ $id }}">@endif
            @if ($kind === 'type')
                <input type="hidden" name="expense_type" value="{{ $id }}">
            @else
                {{-- A software line takes the software's name; a named line keeps its name. --}}
                <input type="hidden" name="label" value="{{ $row['label'] }}">
            @endif
            <p class="text-sm text-gray-600">For <span class="font-semibold text-gray-900">{{ $row['label'] }}</span></p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($kind === 'type')
                    <div>
                        <x-input-label for="line-exp-label" value="What it was for" />
                        <x-text-input id="line-exp-label" class="mbui-input mt-1" type="text" name="label" :value="old('label', $types[$id]['default_label'] ?? $row['label'])" required />
                        <x-input-error :messages="$errors->get('label')" class="mt-2" />
                    </div>
                @elseif (in_array($kind, ['product', 'tool'], true) && $types)
                    <div>
                        <x-input-label for="line-exp-type" value="Type" />
                        <select id="line-exp-type" name="expense_type" class="mbui-input mt-1">
                            <option value="">Choose type…</option>
                            @foreach ($types as $key => $t)
                                <option value="{{ $key }}" @selected(old('expense_type', 'product_accounts') === $key)>{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <x-input-label for="line-exp-amount" value="Amount (TZS)" />
                    <x-text-input id="line-exp-amount" class="mbui-input mt-1" type="number" step="0.01" min="0.01" name="amount" :value="old('form') === 'expense' ? old('amount') : ''" required />
                    <x-input-error :messages="old('form') === 'expense' ? $errors->get('amount') : []" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="line-exp-date" value="Date paid" />
                    <x-text-input id="line-exp-date" class="mbui-input mt-1" type="date" name="spent_at" :value="old('spent_at', $today)" :max="$today" required />
                    <x-input-error :messages="$errors->get('spent_at')" class="mt-2" />
                </div>
                <x-finance.money-account-select :accounts="$moneyAccounts" id="line-exp-money" label="Paid from" :suggest="true" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="line-exp-notes" value="Notes (optional)" />
                    <x-text-input id="line-exp-notes" class="mbui-input mt-1" type="text" name="description" :value="old('description')" />
                </div>
                @if ($receiptsSupported)
                    <div>
                        <x-input-label for="line-exp-receipt" value="Receipt (optional)" />
                        <input id="line-exp-receipt" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                            class="mbui-input mt-1 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700" />
                        <x-input-error :messages="$errors->get('receipt')" class="mt-2" />
                    </div>
                @endif
            </div>
            <div class="flex justify-end">
                <x-mbui.button type="submit">Save expense</x-mbui.button>
            </div>
        </form>
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
                            <p class="text-xs text-gray-500">{{ collect([$expense->typeLabel(), $expense->moneyAccount ? 'Paid from '.$expense->moneyAccount->name : null, $expense->description, $expense->isAutomatic() ? 'Recorded automatically' : null])->filter()->implode(' · ') }}</p>
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

    @endif

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

    @if ($kind !== 'pool')
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
    @endif

</x-layouts.finance>
