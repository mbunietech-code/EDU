@php
    $methodsArray = $paymentMethods
        ->map(fn ($m) => [
            'code' => $m->code,
            'name' => $m->name,
            'qr' => $m->qrImageUrl(),
            'account' => $m->account_number,
            'instructions' => $m->instructions,
            'link' => $m->link_url,
            'store' => $m->store_url,
        ])
        ->values()
        ->all();
    $defaultMethod = old('payment_method', $paymentMethods->first()?->code ?? '');
@endphp

<x-layouts.user title="Submit Payment" header="Submit Payment">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Submit payment for {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">Amount due: <span class="font-semibold">TZS {{ number_format($order->amount) }}</span></p>
            <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs font-semibold text-gray-600" />
        </div>
        <a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor text-sm">Back to order</a>
    </div>

    @php
        // Manual payment is the fallback when nothing else is switched on.
        $showManual = isset($channelOptions['manual']) || empty($channelOptions);
        $options = $channelOptions;
        if ($showManual && ! isset($options['manual'])) {
            $options['manual'] = ['code' => 'manual', 'name' => 'Pay manually (QR code)', 'kind' => 'manual', 'flow' => 'manual', 'via' => null, 'needs_phone' => false, 'charge' => null];
        }

        $firstOf = fn (array $flows) => collect($options)->first(fn ($o) => in_array($o['flow'], $flows, true))['code'] ?? null;
        $legacyTabs = ['mobile' => $firstOf(['push', 'hosted']), 'card' => $firstOf(['card']), 'paypal' => $firstOf(['paypal'])];

        // Reopen the option whose form came back with errors, else ?tab=, else the first.
        $activeTab = match (true) {
            $errors->hasAny(['phone', 'name', 'channel']) && isset($options[old('channel')]) => old('channel'),
            $errors->hasAny(['card_name', 'card_phone']) => $legacyTabs['card'],
            $errors->hasAny(['gateway', 'network']) => $legacyTabs['mobile'],
            $errors->hasAny(['payment_method', 'payment_proof', 'note', 'amount', 'transaction_reference']) => 'manual',
            default => $legacyTabs[request('tab')] ?? request('tab'),
        };
        if (! isset($options[$activeTab])) {
            $activeTab = array_key_first($options);
        }

        $hint = fn (array $o) => match ($o['flow']) {
            'push' => 'PIN prompt on your phone',
            'hosted' => 'Secure form via '.$o['via'],
            'card' => 'Charged in '.$o['charge']['currency'],
            'paypal' => 'PayPal balance or card',
            default => 'Scan QR and upload proof',
        };
    @endphp

    <div x-data="{
            tab: @js($activeTab),
            show(name) {
                this.tab = name;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState(null, '', url);
            },
        }">

    @if (count($options) > 1)
        <div class="mt-6">
            <p class="text-sm font-medium text-gray-700">How would you like to pay?</p>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3" role="tablist" aria-label="Payment options">
                @foreach ($options as $code => $option)
                    <button type="button" role="tab" @click="show(@js($code))" :aria-selected="tab === @js($code)" data-channel="{{ $code }}"
                        :class="tab === @js($code) ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-200 bg-white hover:border-gray-300'"
                        class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition {{ $activeTab === $code ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-200 bg-white' }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                            <x-payment-kind-icon :kind="$option['kind']" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-gray-900">{{ $option['name'] }}</span>
                            <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $hint($option) }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($options as $code => $option)
        @continue($option['flow'] === 'manual')
        @php
            $mine = old('channel') === $code;
            $phoneId = 'phone-'.$code;
        @endphp
        <div role="tabpanel" data-channel-panel="{{ $code }}" x-show="tab === @js($code)" @if ($activeTab !== $code) x-cloak @endif>
            <div class="mt-6 mbui-card p-6">
                @if ($option['flow'] === 'push')
                    <h2 class="text-base font-semibold text-gray-900">Pay instantly with mobile money</h2>
                    <p class="mt-1 text-sm text-gray-500">We send a payment request to your {{ $option['name'] }} number. Enter your PIN and your order is confirmed automatically, with no screenshot needed.</p>
                @elseif ($option['flow'] === 'hosted')
                    <h2 class="text-base font-semibold text-gray-900">
                        @if ($option['kind'] === 'card') Pay by card (Visa / Mastercard)
                        @elseif ($option['kind'] === 'bank') Pay from your bank
                        @else Pay with {{ $option['name'] }}
                        @endif
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">Press pay and {{ $option['via'] }}'s secure form opens right here on this page{{ $option['kind'] === 'mobile' ? ': enter your '.$option['name'].' number there' : ($option['kind'] === 'card' ? ': enter your card there' : '') }}. You pay <span class="font-semibold text-gray-900">TZS {{ number_format($order->amount) }}</span> and your order is confirmed automatically.</p>
                @elseif ($option['flow'] === 'card')
                    <h2 class="text-base font-semibold text-gray-900">Pay by card (Visa / Mastercard)</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        You will be charged <span class="font-semibold text-gray-900">{{ $option['charge']['currency'] }} {{ number_format($option['charge']['amount'], 2) }}</span>
                        (TZS {{ number_format($order->amount) }} at today's rate) on {{ $option['via'] }}'s secure card form, shown on this page. We never see your card number.
                    </p>
                @else
                    <h2 class="text-base font-semibold text-gray-900">Pay with PayPal or card</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        You will be charged <span class="font-semibold text-gray-900">{{ $option['charge']['currency'] }} {{ number_format($option['charge']['amount'], 2) }}</span>
                        (TZS {{ number_format($order->amount) }} at today's rate) on PayPal's secure page. Your order is confirmed as soon as you return.
                    </p>
                @endif

                <form method="POST" action="{{ route('user.payments.channel.store', $order) }}" class="mt-5 space-y-4" x-data="{ sending: false }" @submit="sending = true">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $code }}">

                    {{-- PesaPal and PayPal ask for the number / card on their own form: one click here. --}}
                    @if (in_array($option['flow'], ['push', 'card'], true))
                        <div class="grid gap-4 sm:grid-cols-2">
                            @if ($option['flow'] === 'card')
                                <div>
                                    <x-input-label :for="'name-'.$code" value="Name on card" />
                                    <x-text-input :id="'name-'.$code" name="name" class="mbui-input mt-1" :value="$mine ? old('name') : auth()->user()->name" required />
                                    @if ($mine) <x-input-error :messages="$errors->get('name')" class="mt-2" /> @endif
                                </div>
                            @endif
                            <div>
                                <x-input-label :for="$phoneId" :value="$option['flow'] === 'push' ? $option['name'].' number' : 'Phone number'" />
                                <x-text-input :id="$phoneId" name="phone" type="tel" inputmode="tel" class="mbui-input mt-1" :value="$mine ? old('phone') : ''" placeholder="0712 345 678" :required="$option['needs_phone']" />
                                @if ($mine) <x-input-error :messages="$errors->get('phone')" class="mt-2" /> @endif
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end border-t border-gray-100 pt-4">
                        @if ($option['flow'] === 'paypal')
                            <button type="submit" x-bind:disabled="sending" class="inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-bold shadow-sm hover:opacity-90 disabled:opacity-60" style="background-color: #FFC439; color: #003087;">
                                <span x-show="! sending">Pay with PayPal</span>
                                <span x-show="sending" x-cloak>Opening PayPal...</span>
                            </button>
                        @else
                            <x-mbui.button type="submit" x-bind:disabled="sending">
                                <span x-show="! sending">
                                    @if ($option['charge'])
                                        Pay {{ $option['charge']['currency'] }} {{ number_format($option['charge']['amount'], 2) }}
                                    @else
                                        Pay TZS {{ number_format($order->amount) }}
                                    @endif
                                </span>
                                <span x-show="sending" x-cloak>{{ $option['flow'] === 'push' ? 'Sending request...' : 'Preparing secure form...' }}</span>
                            </x-mbui.button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    @if ($showManual)
    <div role="tabpanel" x-show="tab === 'manual'" @if ($activeTab !== 'manual') x-cloak @endif>
    <div class="mt-6 mbui-card p-6"
        x-data='{
            selectedMethod: "{{ $defaultMethod }}",
            methods: @json($methodsArray),
            preview: null,
            selectedProofName: "",
            selectedProofIsImage: false,
            submitting: false,
            selected() { return this.methods.find(m => m.code === this.selectedMethod) || {}; },
            payHref() {
                const m = this.selected();
                if (/android/i.test(navigator.userAgent)) return m.link || m.store || "#";
                return m.store || m.link || "#";
            },
            openPay() {
                const m = this.selected();
                if (/android/i.test(navigator.userAgent)) {
                    if (m.link) {
                        window.location.href = m.link;
                    } else if (m.store) {
                        window.open(m.store, "_blank");
                    }
                } else {
                    window.open(m.store || m.link || "#", "_blank");
                }
            },
            copied: false,
            copyAccount() {
                const m = this.selected();
                if (!m.account) return;
                navigator.clipboard.writeText(m.account).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1500);
                });
            }
        }'>

        @if ($paymentMethods->isEmpty())
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                Payment is temporarily unavailable. Please contact support.
            </div>
        @else
            <form method="POST" action="{{ route('user.payments.store', $order) }}" enctype="multipart/form-data" class="space-y-5" @submit="submitting = true">
                @csrf

                <div>
                    <x-input-label value="Choose payment method" />
                    <div class="mt-1 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($paymentMethods as $method)
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-3">
                                <input type="radio" name="payment_method" :value="'{{ $method->code }}'" x-model="selectedMethod"
                                    class="mt-1 h-4 w-4 text-indigo-600" @checked($defaultMethod === $method->code)>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ $method->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $method->description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                </div>

                <template x-if="selectedMethod">
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-5">
                        <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                            <button type="button" x-show="selected().qr" @click="openPay()" class="shrink-0 text-center">
                                <img :src="selected().qr" :alt="selected().name"
                                    class="h-48 w-48 sm:h-40 sm:w-40 rounded-lg border border-gray-200 bg-white object-cover">
                                <template x-if="selected().link || selected().store">
                                    <span class="mt-2 block rounded-lg bg-indigo-600 px-3 py-1.5 text-center text-xs font-semibold text-white">
                                        Tap to open the app
                                    </span>
                                </template>
                            </button>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900" x-text="'Scan the QR code with ' + selected().name"></h3>
                                <p class="mt-2 text-sm text-gray-600" x-text="selected().instructions || 'Complete the payment, then confirm below.'"></p>
                                <template x-if="selected().account">
                                    <div class="mt-3">
                                        <p class="text-xs font-medium text-gray-500">Can't scan? Enter this number manually:</p>
                                        <button type="button" @click="copyAccount()"
                                            class="mt-1 inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-white px-3 py-1.5 font-mono text-sm font-semibold text-gray-900 hover:bg-indigo-50">
                                            <span x-text="selected().account"></span>
                                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                            </svg>
                                        </button>
                                        <span x-show="copied" x-cloak class="ml-2 text-xs font-medium text-emerald-600">Copied!</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="payment_proof" value="Payment proof" />
                        <input id="payment_proof" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                            @change="const f = $event.target.files[0]; selectedProofName = f ? f.name : ''; selectedProofIsImage = !!(f && f.type && f.type.startsWith('image/')); preview = selectedProofIsImage ? URL.createObjectURL(f) : null" required>
                        <p class="mt-1 text-xs text-gray-500">Upload JPG, PNG, GIF, WebP, or PDF. Max 12 MB.</p>
                        <x-input-error :messages="$errors->get('payment_proof')" class="mt-2" />

                        <template x-if="preview">
                            <div class="mt-3">
                                <img :src="preview" alt="Payment proof preview" class="max-h-48 rounded-lg border border-gray-200">
                            </div>
                        </template>
                        <template x-if="selectedProofName && ! selectedProofIsImage">
                            <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <span class="font-medium">Selected file:</span> <span x-text="selectedProofName"></span>
                            </div>
                        </template>
                    </div>

                    <div>
                        <x-input-label for="note" value="Note (optional)" />
                        <textarea id="note" name="note" rows="3" class="mbui-input mt-1" placeholder="Any additional information about this payment">{{ old('note') }}</textarea>
                        <x-input-error :messages="$errors->get('note')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                    <a href="{{ route('user.orders.show', $order) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                    <x-mbui.button type="submit" x-bind:disabled="submitting">Submit payment</x-mbui.button>
                </div>
            </form>
        @endif
    </div>
    </div>
    @endif

    </div>

</x-layouts.user>
