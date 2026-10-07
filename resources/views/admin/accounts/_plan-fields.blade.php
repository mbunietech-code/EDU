{{-- The AI plan we bought for this account (used by the create and edit forms). --}}
@php($account = $account ?? null)

<fieldset class="rounded-xl border border-gray-200 p-4">
    <legend class="px-1 text-sm font-semibold text-gray-900">Plan we bought</legend>
    <p class="text-xs text-gray-500">The plan MbunieEduHub paid the provider for. Shown on the <a href="{{ route('admin.accounts.plans') }}" class="mbui-anchor">AI plans</a> page with the days left.</p>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-input-label for="plan_name" value="Plan (e.g. ChatGPT Plus – monthly)" />
            <x-text-input id="plan_name" class="mbui-input mt-1" type="text" name="plan_name" maxlength="120"
                          :value="old('plan_name', $account?->plan_name)" />
            <x-input-error :messages="$errors->get('plan_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="purchased_at" value="Bought on" />
            <x-text-input id="purchased_at" class="mbui-input mt-1" type="date" name="purchased_at"
                          :value="old('purchased_at', $account?->purchased_at?->toDateString())" />
            <x-input-error :messages="$errors->get('purchased_at')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="expires_at" value="Ends on" />
            <x-text-input id="expires_at" class="mbui-input mt-1" type="date" name="expires_at"
                          :value="old('expires_at', $account?->expires_at?->toDateString())" />
            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="cost" value="What we paid" />
            <div class="mt-1 flex gap-2">
                <x-text-input id="cost" class="mbui-input" type="number" step="0.01" min="0" name="cost"
                              :value="old('cost', $account?->cost)" />
                <select name="cost_currency" class="mbui-input w-28" aria-label="Currency">
                    @foreach (\App\Models\Account::CURRENCIES as $cur)
                        <option value="{{ $cur }}" @selected(old('cost_currency', $account?->cost_currency ?? 'USD') === $cur)>{{ $cur }}</option>
                    @endforeach
                </select>
            </div>
            <x-input-error :messages="$errors->get('cost')" class="mt-2" />
        </div>
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="auto_renew" value="1" class="rounded border-gray-300 text-indigo-600"
                       @checked(old('auto_renew', $account?->auto_renew))>
                Renews automatically
            </label>
        </div>
    </div>
</fieldset>
