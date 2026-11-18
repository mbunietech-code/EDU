@php
    $activeTab = in_array(request('tab'), ['automatic', 'manual'], true) ? request('tab') : 'channels';
    $liveGateways = collect($onlineGateways)->where('ready', true)->count();
    $enabledManual = $paymentMethods->where('enabled', true)->count();
    $liveChannels = collect($channels)->filter(fn ($c) => $c['enabled'] && $c['gateway_ready'])->count();
@endphp

<x-layouts.admin title="Payment Methods" header="Payment Methods">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payment Methods</h1>
            <p class="mt-1 text-sm text-gray-500">Choose how customers pay at checkout.</p>
        </div>
    </div>

    <div x-data="{
            tab: @js($activeTab),
            show(name) {
                this.tab = name;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState(null, '', url);
            },
        }">

        <div class="mt-6 border-b border-gray-200">
            <nav class="-mb-px flex gap-6 overflow-x-auto" role="tablist" aria-label="Payment method types">
                <button type="button" role="tab" @click="show('channels')" :aria-selected="tab === 'channels'"
                    :class="tab === 'channels' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="inline-flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-semibold {{ $activeTab === 'channels' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500' }}">
                    Checkout order
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $liveChannels }} live</span>
                </button>
                <button type="button" role="tab" @click="show('automatic')" :aria-selected="tab === 'automatic'"
                    :class="tab === 'automatic' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="inline-flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-semibold {{ $activeTab === 'automatic' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500' }}">
                    Gateways
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $liveGateways }} live</span>
                </button>
                <button type="button" role="tab" @click="show('manual')" :aria-selected="tab === 'manual'"
                    :class="tab === 'manual' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    class="inline-flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-semibold {{ $activeTab === 'manual' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500' }}">
                    Manual
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $enabledManual }} on</span>
                </button>
            </nav>
        </div>

    {{-- ================= Checkout order: channels and priority ================= --}}
    <section role="tabpanel" x-show="tab === 'channels'" @if ($activeTab !== 'channels') x-cloak @endif class="mt-6">
        <p class="text-sm text-gray-500">Switch on each way to pay and set the order customers see it in, on the website and in MHub. A channel reaches customers only while its gateway is live under Gateways.</p>

        @unless ($channelsReady)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Apply database change <span class="font-mono">0046_2026_11_18_payment_channels.sql</span> in Admin → Database to save channels and their order. Until then checkout shows every live gateway as before.
            </div>
        @endunless

        <form method="POST" action="{{ route('admin.payment-methods.channels.update') }}" class="mt-4"
            x-data="{
                items: @js($channels),
                dragging: null,
                changed: false,
                move(from, to) {
                    if (to < 0 || to >= this.items.length || from === to) return;
                    const [item] = this.items.splice(from, 1);
                    this.items.splice(to, 0, item);
                    this.changed = true;
                },
                toggle(item) { item.enabled = ! item.enabled; this.changed = true; },
                drop(index) { if (this.dragging !== null) this.move(this.dragging, index); this.dragging = null; },
            }">
            @csrf
            @method('PUT')

            <div class="mbui-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Checkout order</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Top of the list shows first. Drag a row or use the arrows, then save.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span x-show="changed" x-cloak class="text-xs font-medium text-amber-700">Unsaved changes</span>
                        <x-mbui.button type="submit" :disabled="! $channelsReady">Save order</x-mbui.button>
                    </div>
                </div>

                <ol class="divide-y divide-gray-100">
                    <template x-for="(item, index) in items" :key="item.code">
                        <li class="flex items-center gap-3 px-3 py-3 sm:px-6"
                            draggable="true"
                            @dragstart="dragging = index; $event.dataTransfer.effectAllowed = 'move'"
                            @dragover.prevent
                            @drop.prevent="drop(index)"
                            @dragend="dragging = null"
                            :class="dragging === index ? 'bg-gray-50 opacity-60' : 'bg-white'">
                            <input type="hidden" :name="'channels[' + index + '][code]'" :value="item.code">
                            <input type="hidden" :name="'channels[' + index + '][enabled]'" :value="item.enabled ? 1 : 0">

                            <span class="hidden cursor-grab text-gray-300 hover:text-gray-500 sm:block" title="Drag to reorder">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                                </svg>
                            </span>
                            <span class="w-5 text-right text-sm font-semibold tabular-nums text-gray-400" x-text="index + 1"></span>

                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                                <x-payment-kind-icon kind="mobile" x-show="item.kind === 'mobile'" />
                                <x-payment-kind-icon kind="card" x-show="item.kind === 'card'" x-cloak />
                                <x-payment-kind-icon kind="bank" x-show="item.kind === 'bank'" x-cloak />
                                <x-payment-kind-icon kind="wallet" x-show="item.kind === 'wallet'" x-cloak />
                                <x-payment-kind-icon kind="manual" x-show="item.kind === 'manual'" x-cloak />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900" x-text="item.name"></p>
                                <p class="truncate text-xs text-gray-500">
                                    <span class="font-medium text-gray-700" x-text="'via ' + item.gateway_label"></span>
                                    <span class="hidden sm:inline" x-text="' · ' + (item.description || '')"></span>
                                </p>
                            </div>

                            <span class="hidden shrink-0 rounded-full px-2 py-0.5 text-xs font-medium md:inline-block"
                                :class="item.enabled && item.gateway_ready ? 'bg-emerald-50 text-emerald-700' : (item.enabled ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500')"
                                x-text="item.enabled && item.gateway_ready ? 'Live' : (item.enabled ? (item.kind === 'manual' ? 'No QR codes yet' : 'Gateway off') : 'Off')"></span>

                            <button type="button" role="switch" :aria-checked="item.enabled.toString()" @click="toggle(item)"
                                :aria-label="(item.enabled ? 'Switch off ' : 'Switch on ') + item.name + ' via ' + item.gateway_label"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                :class="item.enabled ? 'bg-indigo-600' : 'bg-gray-200'">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform"
                                    :class="item.enabled ? 'translate-x-5' : 'translate-x-0.5'"></span>
                            </button>

                            <div class="flex shrink-0 flex-col">
                                <button type="button" @click="move(index, index - 1)" :disabled="index === 0"
                                    class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" aria-label="Move up">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                </button>
                                <button type="button" @click="move(index, index + 1)" :disabled="index === items.length - 1"
                                    class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" aria-label="Move down">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>
        </form>

        <p class="mt-3 text-xs text-gray-500">Mobile money through ClickPesa or AzamPay sends a PIN prompt to the customer's phone. PesaPal and ClickPesa card forms open inside our own checkout page, so customers never leave MbunieHub. PayPal is the only one that opens PayPal's own page.</p>
    </section>

    {{-- ================= Automatic (online) payments ================= --}}
    <section role="tabpanel" x-show="tab === 'automatic'" @if ($activeTab !== 'automatic') x-cloak @endif class="mt-6">
        <p class="text-sm text-gray-500">The customer pays online and the order is verified, marked paid and receipted by email automatically. Admins get an email for every payment. After a gateway is live, switch its channels on under <button type="button" @click="show('channels')" class="font-semibold text-indigo-600 hover:text-indigo-500">Checkout order</button>.</p>

        <div class="mt-4 grid gap-6 xl:grid-cols-3">
            @foreach ($onlineGateways as $key => $gw)
                <div class="mbui-card flex flex-col p-6" x-data="{ showKeys: {{ $gw['ready'] ? 'false' : 'true' }} }">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $gw['label'] }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                @if ($key === 'azampay') M-Pesa, Mixx by Yas, Airtel, Halopesa, AzamPesa (USSD push)
                                @elseif ($key === 'clickpesa') All Tanzanian mobile money (USSD push)
                                @elseif ($key === 'clickpesa_card') Visa, Mastercard, Amex, UnionPay via ClickPesa, charged in USD
                                @elseif ($key === 'pesapal') M-Pesa, Airtel Money, Mixx by Yas, cards and bank in TZS, on a PesaPal form inside checkout
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
                                    Gateway on
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

                        @if ($key === 'clickpesa_card')
                            <p class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">Uses the ClickPesa keys and the ClickPesa callback URL above. Card payments only work after ClickPesa approves your KYC.</p>
                        @elseif ($key === 'pesapal')
                            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                                <p>Get the consumer key and secret from your PesaPal merchant dashboard (sandbox keys for Sandbox). The payment notification (IPN) URL is registered with PesaPal automatically.</p>
                                <p class="mt-1.5 font-medium {{ $gw['ipn_id'] ? 'text-emerald-700' : 'text-gray-500' }}">
                                    {{ $gw['ipn_id'] ? 'IPN registered for this environment.' : 'IPN not registered yet: it registers on Test connection or the first payment.' }}
                                </p>
                            </div>
                        @elseif ($gw['callback_url'])
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
    <section role="tabpanel" x-show="tab === 'manual'" @if ($activeTab !== 'manual') x-cloak @endif class="mt-6">
        <p class="text-sm text-gray-500">The customer scans a QR code or types your number, pays, and uploads proof. An admin approves it from Payments.</p>

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
    </section>

    </div>

</x-layouts.admin>