@php
    $repaid = $capitalEntry->repaidAmount();
    $outstanding = $capitalEntry->outstandingAmount();
    $progress = $capitalEntry->amount > 0 ? min(100, round($repaid / $capitalEntry->amount * 100)) : 0;
@endphp

<x-layouts.finance title="Loan Repayments" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Repay loan: {{ $capitalEntry->label }}</h1>
            <p class="mt-1 text-sm text-gray-500">Borrowed from <span class="font-medium text-gray-700">{{ $capitalEntry->source }}</span> on {{ $capitalEntry->created_at->format('d M Y') }}</p>
        </div>
        <a href="{{ route('admin.finance.capital.index') }}" class="mbui-anchor text-sm">Back to capital</a>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-3">
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Borrowed</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">TZS {{ number_format($capitalEntry->amount) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Repaid</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600">TZS {{ number_format($repaid) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Still owed</p>
            <p class="mt-2 text-2xl font-bold tracking-tight {{ $outstanding > 0 ? 'text-amber-600' : 'text-gray-900' }}">TZS {{ number_format($outstanding) }}</p>
        </x-mbui.card>
    </div>

    <div class="mt-4">
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ $progress }}% repaid</p>
    </div>

    @if ($outstanding > 0)
        <div class="mt-6 mbui-card p-6">
            <h2 class="text-base font-semibold text-gray-900">Record a repayment</h2>
            <form method="POST" action="{{ route('admin.finance.capital.repayments.store', $capitalEntry) }}" class="mt-4 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="rep-amount" value="Amount (TZS)" />
                        <x-text-input id="rep-amount" class="mbui-input mt-1" type="number" step="0.01" min="0.01" :max="$outstanding" name="amount" :value="old('amount', $outstanding)" required />
                        <p class="mt-1 text-xs text-gray-500">You can pay in parts. Max TZS {{ number_format($outstanding) }}.</p>
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="rep-date" value="Date paid" />
                        <x-text-input id="rep-date" class="mbui-input mt-1" type="date" name="paid_at" :value="old('paid_at', now()->toDateString())" :max="now()->toDateString()" required />
                        <x-input-error :messages="$errors->get('paid_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="rep-method" value="Paid via (optional)" />
                        <x-text-input id="rep-method" class="mbui-input mt-1" type="text" name="method" :value="old('method')" placeholder="e.g. M-Pesa, Cash, CRDB transfer" />
                        <x-input-error :messages="$errors->get('method')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="rep-reference" value="Reference (optional)" />
                        <x-text-input id="rep-reference" class="mbui-input mt-1" type="text" name="reference" :value="old('reference')" placeholder="Transaction ID" />
                        <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                    </div>
                </div>
                <div>
                    <x-input-label for="rep-notes" value="Notes (optional)" />
                    <textarea id="rep-notes" name="notes" rows="2" class="mbui-input mt-1">{{ old('notes') }}</textarea>
                </div>
                <div class="flex justify-end border-t border-gray-100 pt-4">
                    <x-mbui.button type="submit">Record repayment</x-mbui.button>
                </div>
            </form>
        </div>
    @else
        <div class="mt-6 rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
            This loan is fully repaid.
        </div>
    @endif

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Repayment history</h2>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Date</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Paid via</th>
                    <th class="mbui-th">Recorded by</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($capitalEntry->repayments as $repayment)
                    <tr>
                        <td class="mbui-td">{{ $repayment->paid_at->format('d M Y') }}</td>
                        <td class="mbui-td font-semibold text-gray-900">
                            TZS {{ number_format($repayment->amount) }}
                            @if ($repayment->notes)
                                <p class="text-xs font-normal text-gray-500">{{ $repayment->notes }}</p>
                            @endif
                        </td>
                        <td class="mbui-td">
                            {{ $repayment->method ?: '-' }}
                            @if ($repayment->reference)
                                <p class="font-mono text-xs text-gray-500">{{ $repayment->reference }}</p>
                            @endif
                        </td>
                        <td class="mbui-td text-gray-500">{{ $repayment->creator?->name ?? '-' }}</td>
                        <td class="mbui-td">
                            <x-mbui.reasoned-action :action="route('admin.finance.capital.repayments.destroy', [$capitalEntry, $repayment])" method="DELETE" label="Remove" prompt-text="Why are you removing this repayment?" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No repayments yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.finance>
