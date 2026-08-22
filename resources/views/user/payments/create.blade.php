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

    <div class="mt-6 mbui-card p-6"
        x-data='{
            selectedMethod: "{{ $defaultMethod }}",
            methods: @json($methodsArray),
            preview: null,
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
                            <img x-show="selected().qr" :src="selected().qr" :alt="selected().name"
                                class="h-48 w-48 shrink-0 sm:h-40 sm:w-40 rounded-lg bg-white border border-gray-200 object-cover">
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
                                <template x-if="selected().link || selected().store">
                                    <div class="mt-3">
                                        <button type="button" @click="openPay()"
                                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                            <span x-text="'Open ' + selected().name + ' app'"></span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="payment_proof" value="Payment proof image" />
                        <input id="payment_proof" type="file" name="payment_proof" accept="image/*"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                            @change="const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)" required>
                        <x-input-error :messages="$errors->get('payment_proof')" class="mt-2" />

                        <template x-if="preview">
                            <div class="mt-3">
                                <img :src="preview" alt="Payment proof preview" class="max-h-48 rounded-lg border border-gray-200">
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

</x-layouts.user>