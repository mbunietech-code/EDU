<x-layouts.finance title="Edit transfer" header="Finance">

    <div class="mbui-page-header">
        <div>
            <a href="{{ $back }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back</a>
            <h1 class="mbui-title mt-1">Edit transfer</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $transfer->fromAccount?->name }} → {{ $transfer->toAccount?->name }} · TZS {{ number_format($transfer->amount) }} on {{ $transfer->transferred_at->format('d M Y') }}. Use the day the money really moved, so each statement shows the right balance on every date.</p>
        </div>
    </div>

    @php($today = \App\Support\FinanceDates::today())
    <div class="mt-6 mbui-card max-w-2xl p-6">
        <form method="POST" action="{{ route('admin.finance.money.transfers.update', $transfer) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="back" value="{{ $back }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="tr-from" value="From" />
                    <select id="tr-from" name="from_account_id" class="mbui-input mt-1" required>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" @selected((string) old('from_account_id', $transfer->from_account_id) === (string) $acc->id)>{{ $acc->name }}{{ $acc->is_active ? '' : ' (archived)' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="tr-to" value="To" />
                    <select id="tr-to" name="to_account_id" class="mbui-input mt-1" required>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" @selected((string) old('to_account_id', $transfer->to_account_id) === (string) $acc->id)>{{ $acc->name }}{{ $acc->is_active ? '' : ' (archived)' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="tr-amount" value="Amount (TZS)" />
                    <x-text-input id="tr-amount" class="mbui-input mt-1" type="number" step="0.01" min="0.01" name="amount" :value="old('amount', (float) $transfer->amount)" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="tr-date" value="Date" />
                    <x-text-input id="tr-date" class="mbui-input mt-1" type="date" name="transferred_at" :value="old('transferred_at', $transfer->transferred_at->toDateString())" :max="$today" required />
                    <x-input-error :messages="$errors->get('transferred_at')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="tr-ref" value="Reference (optional)" />
                    <x-text-input id="tr-ref" class="mbui-input mt-1" type="text" name="reference" :value="old('reference', $transfer->reference)" placeholder="Transaction ID" />
                    <x-input-error :messages="$errors->get('reference')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="tr-notes" value="Notes (optional)" />
                    <x-text-input id="tr-notes" class="mbui-input mt-1" type="text" name="notes" :value="old('notes', $transfer->notes)" />
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ $back }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                <x-mbui.button type="submit">Save</x-mbui.button>
            </div>
        </form>
    </div>

    <div class="mt-4 flex max-w-2xl items-center justify-between gap-3 rounded-lg px-6 py-3 ring-1 ring-red-200">
        <p class="text-sm text-gray-600">Typed in by mistake? Remove it: both statements change back.</p>
        <x-mbui.reasoned-action :action="route('admin.finance.money.transfers.destroy', $transfer)" method="DELETE" label="Remove transfer" prompt-text="Why are you removing this transfer?" />
    </div>

</x-layouts.finance>
