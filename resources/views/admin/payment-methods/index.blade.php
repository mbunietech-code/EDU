<x-layouts.admin title="Payment Methods" header="Payment Methods">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payment Methods</h1>
            <p class="mt-1 text-sm text-gray-500">Automatic online payments (AzamPay, ClickPesa, PayPal) and manual QR payments shown at checkout.</p>
        </div>
    </div>

    {{-- ================= Automatic (online) payments ================= --}}
    <section class="mt-8">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Automatic payments</h2>
                <p class="mt-1 text-sm text-gray-500">The customer pays online and the order is verified, marked paid and receipted by email automatically. Admins get an email for every payment.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-6 xl:grid-cols-3">
            @foreach ($onlineGateways as $key => $gw)
                <div class="mbui-card flex flex-col p-6" x-data="{ showKeys: {{ $gw['ready'] ? 'false' : 'true' }} }">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $gw['label'] }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                @if ($key === 'azampay') M-Pesa, Mixx by Yas, Airtel, Halopesa, AzamPesa (USSD push)
                                @elseif ($key === 'clickpesa') All Tanzanian mobile money (USSD push)
                                @else PayPal balance or card, charged in USD
                                @endif
                            </p>
                        </div>
                        @if ($gw['ready'])
                            <x-mbui.badge appearance="success">Live at checkout</x-mbui.badge>
                        @elseif ($gw['values']['enabled'] ?? false)
                            <x-mbui.badge appearance="warning">Missing keys</x-mbui.badge>
                        @else
                            <x-mbui.badge appearance="neutral">Off</x-mbui.badge>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.payment-methods.gateways.update', $key) }}" class="mt-5 flex flex-1 flex-col space-y-4">
                        @csrf
                        @method('PUT')

                        @foreach ($gw['fields'] as $field => [$label, $type])
                            @if ($type === 'toggle')
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <input type="checkbox" name="{{ $field }}" value="1" @checked($gw['values'][$field]) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    Show at checkout
                                </label>
                            @elseif ($type === 'mode')
                                <div>
                                    <x-input-label :for="$key.'-'.$field" :value="$label" />
                                    <select id="{{ $key }}-{{ $field }}" name="{{ $field }}" class="mbui-input mt-1">
                                        <option value="sandbox" @selected(($gw['values'][$field] ?? 'sandbox') !== 'live')>Sandbox (testing)</option>
                                        <option value="live" @selected(($gw['values'][$field] ?? '') === 'live')>Live (real money)</option>
                                    </select>
                                </div>
                            @else
                                <div x-show="showKeys" x-cloak>
                                    <x-input-label :for="$key.'-'.$field" :value="$label" />
                                    @if ($type === 'secret')
                                        <input id="{{ $key }}-{{ $field }}" name="{{ $field }}" type="password" autocomplete="new-password" class="mbui-input mt-1"
                                            placeholder="{{ $gw['values'][$field] ? 'Saved. Leave blank to keep' : 'Paste here' }}">
                                    @else
                                        <input id="{{ $key }}-{{ $field }}" name="{{ $field }}" type="text" autocomplete="off" class="mbui-input mt-1" value="{{ $gw['values'][$field] }}">
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        <button type="button" x-show="! showKeys" @click="showKeys = true" class="text-left text-xs font-semibold text-indigo-600 hover:text-indigo-500">Edit keys</button>

                        @if ($gw['callback_url'])
                            <div class="rounded-lg bg-gray-50 p-3" x-data="{ copied: false }">
                                <p class="text-xs font-medium text-gray-600">Callback URL (paste on the {{ $gw['label'] }} dashboard)</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <code class="block flex-1 truncate text-xs text-gray-800">{{ $gw['callback_url'] }}</code>
                                    <button type="button" class="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                                        @click="navigator.clipboard.writeText(@js($gw['callback_url'])).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                                        x-text="copied ? 'Copied' : 'Copy'"></button>
                                </div>
                            </div>
                        @else
                            <p class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">No callback URL needed: payments are confirmed with PayPal when the customer returns, and by the background check every minute.</p>
                        @endif

                        <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-4">
                            <button type="submit" form="test-{{ $key }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Test connection</button>
                            <x-mbui.button type="submit">Save</x-mbui.button>
                        </div>
                    </form>
                    <form id="test-{{ $key }}" method="POST" action="{{ route('admin.payment-methods.gateways.test', $key) }}" class="hidden">@csrf</form>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between">
            <p>Callback URLs contain a secret. If one leaks, make new ones and update them on the AzamPay and ClickPesa dashboards.</p>
            <form method="POST" action="{{ route('admin.payment-methods.callback-token') }}" onsubmit="return confirm('Create new callback URLs? The old ones stop working immediately.')">
                @csrf
                <button type="submit" class="font-semibold text-red-600 hover:text-red-500">Create new callback URLs</button>
            </form>
        </div>
    </section>

    {{-- ================= Manual payments ================= --}}
    <section class="mt-10">
        <h2 class="text-lg font-semibold text-gray-900">Manual payments</h2>
        <p class="mt-1 text-sm text-gray-500">The customer scans a QR code or types your number, pays, and uploads proof. An admin approves it from Payments.</p>
    </section>

    <div class="mt-6 mbui-card p-6">
        <h2 class="text-base font-semibold text-gray-900">Add payment method</h2>
        <p class="mt-1 text-sm text-gray-500">A method appears at checkout as soon as you upload its QR code. Provide an app link to enable tap-QR-to-open-app.</p>

        <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="enabled" value="1">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="new-code" value="Code (unique, e.g. mpesa)" />
                    <x-text-input id="new-code" class="mbui-input mt-1" type="text" name="code" :value="old('code')" placeholder="mpesa" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="new-name" value="Name" />
                    <x-text-input id="new-name" class="mbui-input mt-1" type="text" name="name" :value="old('name')" placeholder="M-Pesa" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="new-sort_order" value="Sort order" />
                    <x-text-input id="new-sort_order" class="mbui-input mt-1" type="number" min="0" name="sort_order" :value="old('sort_order', 4)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="new-description" value="Description" />
                    <x-text-input id="new-description" class="mbui-input mt-1" type="text" name="description" :value="old('description')" />
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="new-account_number" value="Account / phone number" />
                    <x-text-input id="new-account_number" class="mbui-input mt-1" type="text" name="account_number" :value="old('account_number')" placeholder="e.g. 0712 345 678" />
                    <x-input-error :messages="$errors->get('account_number')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-400">Shown to buyers so they can type it in manually if they can't scan the QR.</p>
                </div>
            </div>
            <div>
                <x-input-label for="new-instructions" value="Payment instructions" />
                <textarea id="new-instructions" name="instructions" rows="2" class="mbui-input mt-1" placeholder="Open the app, scan the QR code, enter the exact amount and complete the payment.">{{ old('instructions') }}</textarea>
                <x-input-error :messages="$errors->get('instructions')" class="mt-2" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="new-link_url" value="App link (tap QR opens app)" />
                    <x-text-input id="new-link_url" class="mbui-input mt-1" type="text" name="link_url" :value="old('link_url')" placeholder="intent://#Intent;package=...;S.browser_fallback_url=...;end" />
                    <x-input-error :messages="$errors->get('link_url')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-400">Android deep link / intent URL used when the customer taps the QR on a phone.</p>
                </div>
                <div>
                    <x-input-label for="new-store_url" value="App store link (fallback)" />
                    <x-text-input id="new-store_url" class="mbui-input mt-1" type="text" name="store_url" :value="old('store_url')" placeholder="https://play.google.com/store/apps/details?id=..." />
                    <x-input-error :messages="$errors->get('store_url')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-400">Opens when the app cannot be launched (iPhone/desktop or app not installed).</p>
                </div>
            </div>
            <div class="flex justify-end border-t border-gray-100 pt-4">
                <x-mbui.button type="submit">Add payment method</x-mbui.button>
            </div>
        </form>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @forelse ($paymentMethods as $method)
            <div class="mbui-card p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ $method->name }}</h2>
                        <p class="text-xs font-mono text-gray-400">{{ $method->code }}</p>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-wide {{ $method->enabled ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $method->enabled ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $method->name }}">
                    <input type="hidden" name="enabled" value="0">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium text-gray-700" for="enabled-{{ $method->id }}">Enable at checkout</label>
                        <input type="checkbox" name="enabled" id="enabled-{{ $method->id }}" value="1"
                            class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            @checked(old('enabled', $method->enabled))>
                    </div>
                    <div>
                        <x-input-label for="description-{{ $method->id }}" value="Description" />
                        <x-text-input id="description-{{ $method->id }}" class="mbui-input mt-1" type="text" name="description" :value="old('description', $method->description)" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="account_number-{{ $method->id }}" value="Account / phone number" />
                        <x-text-input id="account_number-{{ $method->id }}" class="mbui-input mt-1" type="text" name="account_number" :value="old('account_number', $method->account_number)" placeholder="e.g. 0712 345 678" />
                        <x-input-error :messages="$errors->get('account_number')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-400">Shown to buyers so they can type it in manually if they can't scan the QR.</p>
                    </div>
                    <div>
                        <x-input-label for="instructions-{{ $method->id }}" value="Payment instructions" />
                        <textarea id="instructions-{{ $method->id }}" name="instructions" rows="3" class="mbui-input mt-1">{{ old('instructions', $method->instructions) }}</textarea>
                        <x-input-error :messages="$errors->get('instructions')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="link_url-{{ $method->id }}" value="App link (tap QR opens app)" />
                        <x-text-input id="link_url-{{ $method->id }}" class="mbui-input mt-1" type="text" name="link_url" :value="old('link_url', $method->link_url)"
                            placeholder="intent://#Intent;package=tz.tigo.mfsapp;S.browser_fallback_url=...;end" />
                        <x-input-error :messages="$errors->get('link_url')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-400">Android deep link / intent URL used when the customer taps the QR on a phone.</p>
                    </div>
                    <div>
                        <x-input-label for="store_url-{{ $method->id }}" value="App store link (fallback)" />
                        <x-text-input id="store_url-{{ $method->id }}" class="mbui-input mt-1" type="text" name="store_url" :value="old('store_url', $method->store_url)"
                            placeholder="https://play.google.com/store/apps/details?id=tz.tigo.mfsapp" />
                        <x-input-error :messages="$errors->get('store_url')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-400">Opens when the app cannot be launched (iPhone/desktop or app not installed).</p>
                    </div>
                    <div>
                        <x-input-label for="sort_order-{{ $method->id }}" value="Sort order" />
                        <x-text-input id="sort_order-{{ $method->id }}" class="mbui-input mt-1" type="number" min="0" name="sort_order" :value="old('sort_order', $method->sort_order)" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-mbui.button type="submit">Save details</x-mbui.button>
                    </div>
                </form>

                <div class="mt-6 border-t border-gray-100 pt-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">QR code</h3>
                        @if ($method->qrImageUrl())
                            <form method="POST" action="{{ route('admin.payment-methods.qr.remove', $method) }}" onsubmit="return confirm('Remove this QR code?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800">Remove</button>
                            </form>
                        @endif
                    </div>

                    @if ($method->qrImageUrl())
                        <img src="{{ $method->qrImageUrl() }}" alt="{{ $method->name }} QR code"
                            class="mt-3 h-32 w-32 rounded-lg border border-gray-200 object-cover">
                    @else
                        <p class="mt-3 text-sm text-gray-400">No QR code uploaded yet.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.payment-methods.qr', $method) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <input type="file" name="qr_image" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        <x-input-error :messages="$errors->get('qr_image')" class="mt-2" />
                        <div class="mt-3 flex justify-end">
                            <x-mbui.button type="submit" variant="secondary">Upload QR</x-mbui.button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No payment methods configured.</p>
        @endforelse
    </div>

</x-layouts.admin>