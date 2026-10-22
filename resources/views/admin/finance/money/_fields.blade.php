{{-- Fields shared by "Add money account" and "Edit". $account is null when adding. --}}
@php($chosen = old('payment_methods', $account?->methodCodes() ?? []))
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <x-input-label for="ma-name" value="Name" />
        <x-text-input id="ma-name" class="mbui-input mt-1" type="text" name="name" :value="old('name', $account?->name)" placeholder="e.g. Alipay, CRDB, Petty cash" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="ma-type" value="Type" />
        <select id="ma-type" name="type" class="mbui-input mt-1">
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $account?->type ?? 'mobile_money') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="ma-number" value="Number (optional)" />
        <x-text-input id="ma-number" class="mbui-input mt-1" type="text" name="number" :value="old('number', $account?->number)" placeholder="Phone, account or wallet ID" />
    </div>
    <div>
        <x-input-label for="ma-opening" value="Opening balance (TZS)" />
        <x-text-input id="ma-opening" class="mbui-input mt-1" type="number" step="0.01" name="opening_balance" :value="old('opening_balance', $account ? (float) $account->opening_balance : 0)" />
        <x-input-error :messages="$errors->get('opening_balance')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="ma-opening-date" value="Opening balance date (optional)" />
        <x-text-input id="ma-opening-date" class="mbui-input mt-1" type="date" name="opening_date" :value="old('opening_date', $account?->opening_date?->toDateString())" />
        <p class="mt-1 text-xs text-gray-500">Leave empty to count every movement. With a date, earlier movements are taken as already inside the opening balance.</p>
        <x-input-error :messages="$errors->get('opening_date')" class="mt-2" />
    </div>
    @if ($account)
        <label class="flex items-center gap-2 self-center text-sm text-gray-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            In use (untick to archive)
        </label>
    @endif
</div>

<fieldset>
    <legend class="text-sm font-medium text-gray-700">Customer payments that land here</legend>
    <p class="mt-0.5 text-xs text-gray-500">Approved order payments made with these methods count as money in. Each method can land in one account only.</p>
    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
        @foreach ($methodChoices as $code => $label)
            @php($elsewhere = isset($methodMap[$code]) && $methodMap[$code]->id !== $account?->id ? $methodMap[$code] : null)
            <label class="flex items-center gap-2 text-sm {{ $elsewhere ? 'text-gray-400' : 'text-gray-700' }}">
                <input type="checkbox" name="payment_methods[]" value="{{ $code }}" @checked(in_array($code, $chosen, true)) @disabled($elsewhere)
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                {{ $label }}@if ($elsewhere) <span class="text-xs">(in {{ $elsewhere->name }})</span>@endif
            </label>
        @endforeach
    </div>
</fieldset>

<div>
    <x-input-label for="ma-notes" value="Notes (optional)" />
    <textarea id="ma-notes" name="notes" rows="2" class="mbui-input mt-1">{{ old('notes', $account?->notes) }}</textarea>
</div>
