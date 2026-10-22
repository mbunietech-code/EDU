@props(['accounts', 'selected' => null, 'id' => 'money_account_id', 'label' => 'Money account', 'hint' => null])

{{-- Which money account ("mfuko") this went through. Renders nothing until
     the money accounts database change is applied and an account exists. --}}
@if ($accounts->isNotEmpty())
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
