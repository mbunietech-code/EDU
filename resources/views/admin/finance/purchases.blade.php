<x-layouts.finance title="Purchases" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Purchases</h1>
            <p class="mt-1 text-sm text-gray-500">Product accounts and company assets we pay for. Record each payment as an expense, or link it to one you already typed in, so nothing is missed or counted twice.</p>
        </div>
    </div>

    @if (! $ready)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            Apply <span class="font-mono">0035_2026_10_22_finance_expense_types.sql</span> under <a href="{{ route('admin.database.index') }}" class="font-semibold underline">Admin → Database</a> first.
        </div>
    @else
        @foreach ([
            'account' => ['Product accounts', $accounts, 'Accounts with a cost under Accounts → Plans. Recorded as product costs (COGS) on their product.'],
            'asset' => ['Company assets', $assets, 'Domain, hosting, servers, subscriptions and equipment under Company → Assets.'],
        ] as $kind => [$title, $items, $hint])
            <div class="mt-6 mbui-card overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
                </div>
                @forelse ($items as $item)
                    @php
                        $currency = $kind === 'account' ? ($item->cost_currency ?: 'TZS') : ($item->currency ?: 'TZS');
                        $tzs = $purchases->costInTzs((float) $item->cost, $currency);
                        $done = $recorded[$kind][$item->id] ?? collect();
                        $paidOn = $item->purchased_at?->toDateString();
                        $suggestDate = $paidOn && $paidOn <= now()->toDateString() ? $paidOn : now()->toDateString();
                    @endphp
                    <div class="border-b border-gray-100 px-6 py-4 last:border-b-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">
                                    @if ($kind === 'account')
                                        {{ $item->product?->name ?? 'No product' }}@if ($item->plan_name) · {{ $item->plan_name }}@endif
                                    @else
                                        {{ $item->categoryLabel() }} · {{ $item->cycleLabel() }}@if ($item->vendor) · {{ $item->vendor }}@endif
                                    @endif
                                    · {{ rtrim(rtrim(number_format((float) $item->cost, 2), '0'), '.') }} {{ $currency }}
                                    @if ($currency !== 'TZS' && $tzs) (≈ TZS {{ number_format($tzs) }} today)@endif
                                </p>
                                @if ($done->isNotEmpty())
                                    <p class="mt-1 text-xs text-emerald-700">Recorded:
                                        @foreach ($done as $e)
                                            <a href="{{ route('admin.finance.expenses.edit', $e) }}" class="underline">{{ $e->spent_at->format('M Y') }} · TZS {{ number_format($e->amount) }}</a>@if (! $loop->last), @endif
                                        @endforeach
                                    </p>
                                @else
                                    <p class="mt-1 text-xs text-amber-700">Not recorded in Finance yet.</p>
                                @endif
                            </div>
                        </div>

                        <details class="mt-2">
                            <summary class="cursor-pointer text-sm font-semibold text-indigo-600">Record a payment</summary>
                            <form method="POST" action="{{ route('admin.finance.purchases.record', [$kind, $item->id]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                @csrf
                                <div>
                                    <x-input-label for="amt-{{ $kind }}-{{ $item->id }}" value="Amount paid (TZS)" />
                                    <input id="amt-{{ $kind }}-{{ $item->id }}" type="number" step="0.01" min="0.01" name="amount" value="{{ $tzs ? round($tzs) : '' }}" required class="mbui-input mt-1 w-40">
                                </div>
                                <div>
                                    <x-input-label for="date-{{ $kind }}-{{ $item->id }}" value="Date paid" />
                                    <input id="date-{{ $kind }}-{{ $item->id }}" type="date" name="spent_at" value="{{ $suggestDate }}" max="{{ now()->toDateString() }}" required class="mbui-input mt-1">
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
                                <x-finance.money-account-select :accounts="$moneyAccounts" id="ma-{{ $kind }}-{{ $item->id }}" label="Paid from" />
                                <x-mbui.button type="submit">Record expense</x-mbui.button>
                            </form>
                        </details>

                        @if ($linkable->isNotEmpty())
                            <details class="mt-1">
                                <summary class="cursor-pointer text-sm font-semibold text-gray-600">Already typed in under Expenses? Link it</summary>
                                <form method="POST" action="{{ route('admin.finance.purchases.link', [$kind, $item->id]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <div>
                                        <x-input-label for="link-{{ $kind }}-{{ $item->id }}" value="Existing expense" />
                                        <select id="link-{{ $kind }}-{{ $item->id }}" name="expense_id" class="mbui-input mt-1" required>
                                            @foreach ($linkable as $e)
                                                <option value="{{ $e->id }}">{{ $e->spent_at->format('d M Y') }} · {{ $e->label }} · TZS {{ number_format($e->amount) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-mbui.button type="submit" variant="secondary">Link</x-mbui.button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="px-6 py-4 text-sm text-gray-500">Nothing with a cost yet.</p>
                @endforelse
            </div>
        @endforeach
    @endif

</x-layouts.finance>
