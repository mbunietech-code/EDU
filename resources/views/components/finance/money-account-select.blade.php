@props(['accounts', 'selected' => null, 'id' => 'money_account_id', 'label' => 'Money account', 'hint' => null, 'suggest' => false])

{{-- Which money account ("mfuko") this went through. Renders nothing until
     the money accounts database change is applied and an account exists.
     suggest: a new entry starts on the account used last (only one account: that one),
     so it counts in a balance without being assigned later. --}}
@if ($accounts->isNotEmpty())
    @php
        if ($suggest && $selected === null) {
            $selected = $accounts->count() === 1 ? $accounts->first()->id : app(\App\Services\Finance\MoneyAccountService::class)->lastUsedId();
        }
    @endphp
    <div {{ $attributes }}>
        <x-input-label :for="$id" :value="$label" />
        <select id="{{ $id }}" name="money_account_id" class="mbui-input mt-1">
            <option value="">Not assigned yet</option>
            @foreach ($accounts as $acc)
                <option value="{{ $acc->id }}" @selected((string) old('money_account_id', $selected) === (string) $acc->id)>{{ $acc->name }}</option>
            @endforeach
        </select>
        @if ($hint)
            <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
        @endif
        <x-input-error :messages="$errors->get('money_account_id')" class="mt-2" />
    </div>
@endif
