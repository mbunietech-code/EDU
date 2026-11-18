<x-layouts.admin title="Add Account" header="Add Account">

    @php
        $mode = old('mode', request('mode')) === 'bulk' ? 'bulk' : 'one';
        $productId = old('product_id', request('product_id'));
    @endphp

    <div class="max-w-2xl" x-data="{ mode: @js($mode) }">
        <x-partials.flash />

        <div class="mbui-page-header">
            <div>
                <h1 class="mbui-title">Add account</h1>
                <p class="mt-1 text-sm text-gray-500">A login customers receive after paying. Stored encrypted; customers only see their own.</p>
            </div>
        </div>

        <div class="mt-4 inline-flex rounded-lg border border-gray-200 bg-white p-1" role="tablist" aria-label="How many accounts">
            <button type="button" role="tab" @click="mode = 'one'" :aria-selected="mode === 'one'"
                :class="mode === 'one' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900'"
                class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $mode === 'one' ? 'bg-indigo-600 text-white' : 'text-gray-600' }}">One account</button>
            <button type="button" role="tab" @click="mode = 'bulk'" :aria-selected="mode === 'bulk'"
                :class="mode === 'bulk' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900'"
                class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $mode === 'bulk' ? 'bg-indigo-600 text-white' : 'text-gray-600' }}">Several at once</button>
        </div>

        {{-- One account: product, email, password. Everything else is optional. --}}
        <form method="POST" action="{{ route('admin.accounts.store') }}" class="mt-4 mbui-card p-6 space-y-5" x-show="mode === 'one'" @if ($mode !== 'one') x-cloak @endif>
            @csrf
            <input type="hidden" name="mode" value="one">
            <div>
                <x-input-label for="product_id" value="Product" />
                <select id="product_id" name="product_id" class="mbui-input mt-1" required>
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($productId == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="login_email" value="Login email or username" />
                    <x-text-input id="login_email" class="mbui-input mt-1" type="text" name="login_email" :value="old('login_email')" autocomplete="off" required />
                    <x-input-error :messages="$errors->get('login_email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="login_password" value="Password" />
                    <x-text-input id="login_password" class="mbui-input mt-1" type="text" name="login_password" autocomplete="off" required />
                    <x-input-error :messages="$errors->get('login_password')" class="mt-2" />
                </div>
            </div>

            <details class="group rounded-lg border border-gray-200 px-4 py-3" @if ($errors->hasAny(['name', 'credentials', 'status', 'plan_name', 'purchased_at', 'expires_at', 'cost'])) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-gray-700">More details (optional)</summary>
                <div class="mt-4 space-y-5">
                    <div>
                        <x-input-label for="name" value="Name in the list (default: the login email)" />
                        <x-text-input id="name" class="mbui-input mt-1" type="text" name="name" :value="old('name')" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="credentials" value="Extra login notes for the customer (2FA, workspace...)" />
                        <textarea id="credentials" name="credentials" rows="2" class="mbui-input mt-1 font-mono text-xs">{{ old('credentials') }}</textarea>
                        <x-input-error :messages="$errors->get('credentials')" class="mt-2" />
                    </div>
                    @include('admin.accounts._plan-fields')
                    <div>
                        <x-input-label for="description" value="Admin note" />
                        <textarea id="description" name="description" rows="2" class="mbui-input mt-1">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mbui-input mt-1">
                            @foreach (['available', 'assigned', 'suspended', 'expired', 'maintenance', 'archived'] as $status)
                                <option value="{{ $status }}" @selected(old('status', 'available') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                </div>
            </details>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.accounts.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Add account</x-mbui.button>
            </div>
        </form>

        {{-- Several: one login per line. --}}
        <form method="POST" action="{{ route('admin.accounts.bulk') }}" class="mt-4 mbui-card p-6 space-y-5" x-show="mode === 'bulk'" @if ($mode !== 'bulk') x-cloak @endif>
            @csrf
            <input type="hidden" name="mode" value="bulk">
            <div>
                <x-input-label for="bulk_product_id" value="Product" />
                <select id="bulk_product_id" name="product_id" class="mbui-input mt-1" required>
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($productId == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('product_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="lines" value="Logins, one per line: email:password" />
                <textarea id="lines" name="lines" rows="8" class="mbui-input mt-1 font-mono text-xs" autocomplete="off" spellcheck="false" required
                    placeholder="seat1@example.com:Password1&#10;seat2@example.com:Password2">{{ old('lines') }}</textarea>
                <x-input-error :messages="$errors->get('lines')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">A colon, comma, tab or space between email and password. Up to 200 lines; logins already in the list are skipped.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="bulk_expires_at" value="Plan we bought ends on (optional)" />
                    <x-text-input id="bulk_expires_at" class="mbui-input mt-1" type="date" name="expires_at" :value="old('expires_at')" />
                    <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="bulk_plan_name" value="Plan we bought (optional)" />
                    <x-text-input id="bulk_plan_name" class="mbui-input mt-1" type="text" name="plan_name" :value="old('plan_name')" placeholder="ChatGPT Plus, monthly" />
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.accounts.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Add accounts</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
