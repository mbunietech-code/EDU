@php
    $editing = $asset->exists;
@endphp
<x-layouts.admin :title="$editing ? 'Edit asset' : 'Add asset'" header="Company">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $editing ? 'Edit '.$asset->name : 'Add an asset' }}</h1>
            <p class="mt-1 text-sm text-gray-500">A domain, hosting plan, server, software, licence or device Mbunie pays for.</p>
        </div>
        <a href="{{ route('admin.assets.index') }}" class="mbui-anchor text-sm">Back to assets</a>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.assets.update', $asset) : route('admin.assets.store') }}" class="mt-6 max-w-3xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="mbui-card space-y-4 p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900">What it is</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="mbui-label">Name</label>
                    <input id="name" name="name" type="text" required maxlength="150" class="mbui-input mt-1" value="{{ old('name', $asset->name) }}" placeholder="e.g. mbuniehub.com domain">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <label for="category" class="mbui-label">Category</label>
                    <select id="category" name="category" class="mbui-input mt-1">
                        @foreach (\App\Models\CompanyAsset::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category', $asset->category ?? 'other') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="responsible_id" class="mbui-label">Who looks after it</label>
                    <select id="responsible_id" name="responsible_id" class="mbui-input mt-1">
                        <option value="">— Nobody yet —</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected((int) old('responsible_id', $asset->responsible_id) === $admin->id)>{{ $admin->name }}{{ $admin->job_title ? ' — '.$admin->job_title : '' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('responsible_id')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="mbui-card space-y-4 p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900">Where it is bought</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="vendor" class="mbui-label">Bought from</label>
                    <input id="vendor" name="vendor" type="text" maxlength="150" class="mbui-input mt-1" value="{{ old('vendor', $asset->vendor) }}" placeholder="e.g. Hostinger, Namecheap, Google">
                </div>
                <div>
                    <label for="account" class="mbui-label">Account it is under</label>
                    <input id="account" name="account" type="text" maxlength="150" class="mbui-input mt-1" value="{{ old('account', $asset->account) }}" placeholder="e.g. admin@mbuniehub.com">
                    <p class="mt-1 text-xs text-gray-500">The login email or account name only. Never put passwords here.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="purchase_url" class="mbui-label">Link to buy / renew</label>
                    <input id="purchase_url" name="purchase_url" type="url" maxlength="500" class="mbui-input mt-1" value="{{ old('purchase_url', $asset->purchase_url) }}" placeholder="https://…">
                    <x-input-error :messages="$errors->get('purchase_url')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="mbui-card space-y-4 p-5 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900">Dates and cost</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="purchased_at" class="mbui-label">Bought on</label>
                    <input id="purchased_at" name="purchased_at" type="date" class="mbui-input mt-1" value="{{ old('purchased_at', $asset->purchased_at?->toDateString()) }}">
                    <x-input-error :messages="$errors->get('purchased_at')" class="mt-1" />
                </div>
                <div>
                    <label for="expires_at" class="mbui-label">Ends on</label>
                    <input id="expires_at" name="expires_at" type="date" class="mbui-input mt-1" value="{{ old('expires_at', $asset->expires_at?->toDateString()) }}">
                    <p class="mt-1 text-xs text-gray-500">Leave empty if it never ends. Reminders go out 30, 7 and 1 day(s) before.</p>
                    <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
                </div>
                <div>
                    <label for="billing_cycle" class="mbui-label">Paid</label>
                    <select id="billing_cycle" name="billing_cycle" class="mbui-input mt-1">
                        @foreach (\App\Models\CompanyAsset::CYCLES as $key => $label)
                            <option value="{{ $key }}" @selected(old('billing_cycle', $asset->billing_cycle ?? 'yearly') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label for="cost" class="mbui-label">Cost</label>
                        <input id="cost" name="cost" type="number" step="0.01" min="0" class="mbui-input mt-1" value="{{ old('cost', $asset->cost) }}">
                        <x-input-error :messages="$errors->get('cost')" class="mt-1" />
                    </div>
                    <div class="w-24">
                        <label for="currency" class="mbui-label">Currency</label>
                        <input id="currency" name="currency" type="text" maxlength="3" required list="currencies" class="mbui-input mt-1 uppercase" value="{{ old('currency', $asset->currency ?? 'TZS') }}">
                        <datalist id="currencies"><option value="TZS"><option value="USD"><option value="KES"><option value="EUR"></datalist>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                    <input type="hidden" name="auto_renew" value="0">
                    <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $asset->auto_renew)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    It renews automatically (card on file)
                </label>
            </div>
        </div>

        <div class="mbui-card p-5 sm:p-6">
            <label for="notes" class="mbui-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" maxlength="5000" class="mbui-input mt-1" placeholder="Anything worth knowing: plan name, what depends on it…">{{ old('notes', $asset->notes) }}</textarea>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.assets.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancel</a>
            <x-mbui.button type="submit">{{ $editing ? 'Save changes' : 'Add asset' }}</x-mbui.button>
        </div>
    </form>
</x-layouts.admin>
