<x-layouts.finance title="Money accounts" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Money accounts</h1>
            <p class="mt-1 text-sm text-gray-500">Where the money is: Alipay, WeChat, mobile money, bank, petty cash. Each account has its own statement.</p>
        </div>
    </div>

    @if (! $ready)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            <p class="font-semibold">One database change is needed first.</p>
            <p class="mt-1">Go to <a href="{{ route('admin.database.index') }}" class="font-semibold underline">Admin → Database</a> and apply
                <span class="font-mono">0034_2026_10_22_finance_money_accounts.sql</span>. It only adds new tables and columns; existing finance records are not changed.
                Then come back to this page.</p>
        </div>
    @else
        {{-- Summary: total in active accounts, then each account. --}}
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-mbui.card class="p-6">
                <p class="text-sm font-medium text-gray-500">Money in all accounts</p>
                <p class="mt-2 text-2xl font-bold tracking-tight {{ $total >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($total) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $balances->where('account.is_active', true)->count() }} active {{ \Illuminate\Support\Str::plural('account', $balances->where('account.is_active', true)->count()) }}</p>
            </x-mbui.card>
            <x-mbui.card class="p-6">
                <p class="text-sm font-medium text-gray-500">Not assigned to an account</p>
                <p class="mt-2 text-2xl font-bold tracking-tight {{ $unassigned['totals']['net'] == 0 ? 'text-gray-900' : 'text-amber-600' }}">TZS {{ number_format($unassigned['totals']['net']) }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    @if ($unassigned['totals']['net'] == 0 && $unassigned['totals']['items'] === 0 && $unassigned['payments']->isEmpty())
                        Everything is assigned.
                    @else
                        Money recorded in Finance that no account shows yet. Assign it below.
                    @endif
                </p>
            </x-mbui.card>
        </div>

        <div class="mt-6 mbui-card overflow-hidden">
            <x-mbui.table>
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="mbui-th">Account</th>
                        <th class="mbui-th">Customer payments</th>
                        <th class="mbui-th text-right">Money in</th>
                        <th class="mbui-th text-right">Money out</th>
                        <th class="mbui-th text-right">Balance</th>
                        <th class="mbui-th">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($balances as $b)
                        @php($acc = $b['account'])
                        <tr class="{{ $acc->is_active ? '' : 'opacity-60' }}">
                            <td class="mbui-td whitespace-normal">
                                <a href="{{ route('admin.finance.money.show', $acc) }}" class="font-semibold text-gray-900 hover:text-indigo-600">{{ $acc->name }}</a>
                                <p class="text-xs text-gray-500">{{ $acc->typeLabel() }}@if ($acc->number) · {{ $acc->number }}@endif @unless ($acc->is_active) · Archived @endunless</p>
                            </td>
                            <td class="mbui-td whitespace-normal text-xs text-gray-600">
                                {{ collect($acc->methodCodes())->map(fn ($c) => $methodChoices[$c] ?? $c)->implode(', ') ?: '—' }}
                            </td>
                            <td class="mbui-td text-right tabular-nums text-emerald-700">{{ number_format($b['in']) }}</td>
                            <td class="mbui-td text-right tabular-nums text-red-600">{{ number_format($b['out']) }}</td>
                            <td class="mbui-td text-right font-semibold tabular-nums {{ $b['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($b['balance']) }}</td>
                            <td class="mbui-td">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.finance.money.show', $acc) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Statement</a>
                                    <x-mbui.icon-link :href="route('admin.finance.money.edit', $acc)" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="mbui-td text-center text-gray-500">No money accounts yet. Add the first one below, e.g. Alipay with Alipay payments ticked.</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>
        </div>

        {{-- Not assigned: customer payments with an unmapped method, and records with no account. --}}
        @if ($unassigned['payments']->isNotEmpty() || $unassigned['totals']['items'] > 0)
            <div class="mt-8 mbui-card p-6">
                <h2 class="text-base font-semibold text-gray-900">Not assigned yet</h2>
                <p class="mt-1 text-sm text-gray-500">These were recorded before money accounts existed, or without one. Nothing here is lost; it is just not shown in any account's balance.</p>

                @if ($unassigned['payments']->isNotEmpty())
                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-gray-700">Customer payments</h3>
                        <ul class="mt-2 space-y-1 text-sm text-gray-700">
                            @foreach ($unassigned['payments'] as $p)
                                <li class="flex flex-wrap justify-between gap-2 border-b border-gray-100 py-1">
                                    <span>{{ $p['label'] }} · {{ $p['count'] }} {{ \Illuminate\Support\Str::plural('payment', $p['count']) }}</span>
                                    <span class="font-semibold tabular-nums">TZS {{ number_format($p['total']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-gray-500">Tick the method on the account they land in (Add or Edit account). They then count there automatically, past and future.</p>
                    </div>
                @endif

                @if ($unassigned['totals']['items'] > 0)
                    @if ($accounts->isEmpty())
                        <p class="mt-4 text-sm text-gray-600">Add a money account first, then assign these {{ $unassigned['totals']['items'] }} records to it.</p>
                    @else
                        <form method="POST" action="{{ route('admin.finance.money.assign') }}" class="mt-5 space-y-4">
                            @csrf
                            @foreach ([
                                'expense' => ['Expenses (money out)', $unassigned['expenses'], fn ($r) => $r->spent_at, fn ($r) => $r->label.($r->category ? ' · '.$r->category : ''), -1],
                                'capital' => ['Capital and loans (money in)', $unassigned['capital'], fn ($r) => $r->created_at, fn ($r) => $r->label.' · '.$r->source.($r->is_loan ? ' · loan' : ''), 1],
                                'repayment' => ['Loan repayments (money out)', $unassigned['repayments'], fn ($r) => $r->paid_at, fn ($r) => ($r->capitalEntry?->label ?? 'Loan').' · '.($r->capitalEntry?->source ?? ''), -1],
                            ] as $kind => [$title, $items, $dateOf, $labelOf, $sign])
                                @if ($items->isNotEmpty())
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700">{{ $title }}</h3>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="w-full text-sm">
                                                <tbody class="divide-y divide-gray-100">
                                                    @foreach ($items as $r)
                                                        <tr>
                                                            <td class="w-8 py-1.5"><input type="checkbox" id="as-{{ $kind }}-{{ $r->id }}" name="{{ $kind }}[]" value="{{ $r->id }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></td>
                                                            <td class="whitespace-nowrap py-1.5 pr-3 text-gray-500"><label for="as-{{ $kind }}-{{ $r->id }}">{{ $dateOf($r)?->format('d M Y') }}</label></td>
                                                            <td class="py-1.5 pr-3 text-gray-800"><label for="as-{{ $kind }}-{{ $r->id }}">{{ $labelOf($r) }}</label></td>
                                                            <td class="whitespace-nowrap py-1.5 text-right font-semibold tabular-nums {{ $sign < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $sign < 0 ? '−' : '+' }}{{ number_format($r->amount) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                            <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                                <div>
                                    <x-input-label for="assign-account" value="Ticked records went through" />
                                    <select id="assign-account" name="money_account_id" class="mbui-input mt-1" required>
                                        @foreach ($accounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-mbui.button type="submit">Assign</x-mbui.button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>
        @endif

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="mbui-card p-6">
                <h2 class="text-base font-semibold text-gray-900">Add money account</h2>
                <form method="POST" action="{{ route('admin.finance.money.store') }}" class="mt-4 space-y-4">
                    @csrf
                    @include('admin.finance.money._fields', ['account' => null])
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-mbui.button type="submit">Add account</x-mbui.button>
                    </div>
                </form>
            </div>

            @if ($accounts->count() >= 2)
                <div class="mbui-card p-6 self-start">
                    <h2 class="text-base font-semibold text-gray-900">Move money between accounts</h2>
                    <p class="mt-1 text-sm text-gray-500">E.g. Alipay to CRDB, or CRDB to petty cash. A transfer is not an expense.</p>
                    <form method="POST" action="{{ route('admin.finance.money.transfer') }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="tr-from" value="From" />
                                <select id="tr-from" name="from_account_id" class="mbui-input mt-1" required>
                                    @foreach ($accounts as $acc)
                                        <option value="{{ $acc->id }}" @selected((string) old('from_account_id') === (string) $acc->id)>{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="tr-to" value="To" />
                                <select id="tr-to" name="to_account_id" class="mbui-input mt-1" required>
                                    @foreach ($accounts as $acc)
                                        <option value="{{ $acc->id }}" @selected((string) old('to_account_id', $accounts->get(1)?->id) === (string) $acc->id)>{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="tr-amount" value="Amount (TZS)" />
                                <x-text-input id="tr-amount" class="mbui-input mt-1" type="number" step="0.01" min="0.01" name="amount" :value="old('amount')" required />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="tr-date" value="Date" />
                                <x-text-input id="tr-date" class="mbui-input mt-1" type="date" name="transferred_at" :value="old('transferred_at', now()->toDateString())" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="tr-ref" value="Reference (optional)" />
                                <x-text-input id="tr-ref" class="mbui-input mt-1" type="text" name="reference" :value="old('reference')" placeholder="Transaction ID" />
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-gray-100 pt-4">
                            <x-mbui.button type="submit">Record transfer</x-mbui.button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @endif

</x-layouts.finance>
