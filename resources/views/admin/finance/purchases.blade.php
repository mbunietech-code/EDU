<x-layouts.finance title="Purchases" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Purchases</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500">What we buy: product accounts and company assets. Each purchase and renewal is recorded under
                <a href="{{ route('admin.finance.expenses.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Expenses</a>
                by itself, on the day it was bought and paid from the money account used last. Nothing to do here unless something is listed under “Not in Finance yet”.</p>
        </div>
    </div>

    @if (! $ready)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            Apply <span class="font-mono">0035_2026_10_22_finance_expense_types.sql</span> under <a href="{{ route('admin.database.index') }}" class="font-semibold underline">Admin → Database</a> first.
        </div>
    @else
        @php
            $describe = function (string $kind, $item) use ($purchases) {
                $currency = $kind === 'account' ? ($item->cost_currency ?: 'TZS') : ($item->currency ?: 'TZS');
                $tzs = $purchases->costInTzs((float) $item->cost, $currency);
                $what = $kind === 'account'
                    ? ($item->product?->name ?? 'No product').($item->plan_name ? ' · '.$item->plan_name : '')
                    : $item->categoryLabel().' · '.$item->cycleLabel().($item->vendor ? ' · '.$item->vendor : '');
                $cost = rtrim(rtrim(number_format((float) $item->cost, 2), '0'), '.').' '.$currency
                    .($currency !== 'TZS' && $tzs ? ' (≈ TZS '.number_format($tzs).')' : '');

                return [$what, $cost, $tzs];
            };
            $pendingCount = $pending['account']->count() + $pending['asset']->count();
        @endphp

        {{-- Older purchases: sort each out once --}}
        @if ($pendingCount > 0)
            <div class="mt-6 mbui-card overflow-hidden ring-1 ring-amber-300">
                <div class="border-b border-amber-200 bg-amber-50 px-6 py-4">
                    <h2 class="text-base font-semibold text-amber-900">Not in Finance yet · {{ $pendingCount }}</h2>
                    <p class="mt-1 text-sm text-amber-800">Mostly things bought before purchases recorded themselves (or with a foreign price and no exchange rate yet). For each one, once: if you already typed the payment in under Expenses, choose it under <strong>It is already in Expenses</strong>; otherwise <strong>Record it</strong>.</p>
                </div>
                @foreach (['account' => $pending['account'], 'asset' => $pending['asset']] as $kind => $items)
                    @foreach ($items as $item)
                        @php
                            [$what, $cost, $tzs] = $describe($kind, $item);
                            $paidOn = $item->purchased_at?->toDateString();
                            $today = \App\Support\FinanceDates::today();
                        @endphp
                        <div class="border-b border-gray-100 px-6 py-4 last:border-b-0">
                            <p class="font-semibold text-gray-900">{{ $item->name }}
                                <span class="ml-1 text-xs font-normal text-gray-500">{{ $kind === 'account' ? 'Product account' : 'Company asset' }} · {{ $what }} · {{ $cost }}</span></p>

                            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                                @if ($linkable->isNotEmpty())
                                    <form method="POST" action="{{ route('admin.finance.purchases.link', [$kind, $item->id]) }}" class="rounded-lg bg-gray-50 p-3">
                                        @csrf
                                        <p class="text-sm font-semibold text-gray-800">It is already in Expenses</p>
                                        <div class="mt-2 flex flex-wrap items-end gap-2">
                                            <label class="sr-only" for="link-{{ $kind }}-{{ $item->id }}">Expense</label>
                                            <select id="link-{{ $kind }}-{{ $item->id }}" name="expense_id" class="mbui-input min-w-0 flex-1" required>
                                                @foreach ($linkable as $e)
                                                    <option value="{{ $e->id }}">{{ $e->spent_at->format('d M Y') }} · {{ $e->label }} · TZS {{ number_format($e->amount) }}</option>
                                                @endforeach
                                            </select>
                                            <x-mbui.button type="submit" variant="secondary">That is it</x-mbui.button>
                                        </div>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.finance.purchases.record', [$kind, $item->id]) }}" class="rounded-lg bg-gray-50 p-3">
                                    @csrf
                                    <p class="text-sm font-semibold text-gray-800">Record it</p>
                                    <div class="mt-2 flex flex-wrap items-end gap-2">
                                        <div>
                                            <x-input-label for="amt-{{ $kind }}-{{ $item->id }}" value="Amount (TZS)" />
                                            <input id="amt-{{ $kind }}-{{ $item->id }}" type="number" step="0.01" min="0.01" name="amount" value="{{ $tzs ? round($tzs) : '' }}" required class="mbui-input mt-1 w-36">
                                        </div>
                                        <div>
                                            <x-input-label for="date-{{ $kind }}-{{ $item->id }}" value="Date paid" />
                                            <input id="date-{{ $kind }}-{{ $item->id }}" type="date" name="spent_at" value="{{ $paidOn && $paidOn <= $today ? $paidOn : $today }}" max="{{ $today }}" required class="mbui-input mt-1">
                                        </div>
                                        @if ($kind === 'asset')
                                            <div>
                                                <x-input-label for="type-{{ $item->id }}" value="Type" />
                                                <select id="type-{{ $item->id }}" name="expense_type" class="mbui-input mt-1">
                                                    @foreach (\App\Models\FinanceExpense::types() as $key => $t)
                                                        <option value="{{ $key }}" @selected($purchases->defaultType('asset', $item) === $key)>{{ $t['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        <x-finance.money-account-select :accounts="$moneyAccounts" id="ma-{{ $kind }}-{{ $item->id }}" label="Paid from" :suggest="true" />
                                        <x-mbui.button type="submit">Record</x-mbui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif

        {{-- Everything we bought, and the expense each purchase became --}}
        @foreach ([
            'account' => ['Product accounts', $accounts, 'From Accounts → Plans (accounts with a cost). Counted as the cost of their product.'],
            'asset' => ['Company assets', $assets, 'From Company → Assets: domain, hosting, servers, subscriptions and equipment.'],
        ] as $kind => [$title, $items, $hint])
            <div class="mt-6 mbui-card overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
                </div>
                <ul class="divide-y divide-gray-100" role="list">
                    @forelse ($items as $item)
                        @php
                            [$what, $cost] = $describe($kind, $item);
                            $done = $recorded[$kind][$item->id] ?? collect();
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">{{ $what }} · {{ $cost }}</p>
                            </div>
                            @if ($done->isNotEmpty())
                                <p class="text-xs text-emerald-700">✓
                                    @foreach ($done as $e)
                                        <a href="{{ route('admin.finance.expenses.edit', $e) }}" class="underline">{{ $e->spent_at->format('d M Y') }} · TZS {{ number_format($e->amount) }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                </p>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Not in Finance yet · see above</span>
                            @endif
                        </li>
                    @empty
                        <li class="px-6 py-4 text-sm text-gray-500">Nothing with a cost yet.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    @endif

</x-layouts.finance>
