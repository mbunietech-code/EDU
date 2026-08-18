@php
    $methodsArray = $paymentMethods
        ->map(fn ($m) => [
            'code' => $m->code,
            'name' => $m->name,
            'qr' => $m->qrImageUrl(),
            'instructions' => $m->instructions,
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
            <x-currency-conversion :amount="$order->amount" class="mt-1 text-xs text-gray-400" />
        </div>
        <a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor text-sm">Back to order</a>
    </div>

    <div class="mt-6 mbui-card p-6"
        x-data='{ selectedMethod: "{{ $defaultMethod }}", methods: @json($methodsArray), preview: null, selected() { return this.methods.find(m => m.code === this.selectedMethod) || {}; } }'>

        @if ($paymentMethods->isEmpty())
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                Payment is temporarily unavailable. Please contact support.
            </div>
        @else
            <form method="POST" action="{{ route('user.payments.store', $order) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <x-input-label value="Choose payment method" />
                    <div class="mt-1 grid gap-3 sm:grid-cols-2">
                        @foreach ($paymentMethods as $method)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4">
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
                                class="h-44 w-44 rounded-lg bg-white border border-gray-200 object-cover">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900" x-text="'Scan the QR code with ' + selected().name"></h3>
                                <p class="mt-2 text-sm text-gray-600" x-text="selected().instructions || 'Complete the payment, then confirm below.'"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="transaction_reference" value="Transaction / reference number" />
                        <x-text-input id="transaction_reference" class="mbui-input mt-1" type="text" name="transaction_reference" :value="old('transaction_reference')" required />
                        <x-input-error :messages="$errors->get('transaction_reference')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" value="Amount paid" />
                        <x-text-input id="amount" class="mbui-input mt-1" type="number" step="0.01" min="0" name="amount" :value="old('amount', $order->amount)" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                </div>

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
                    <x-mbui.button type="submit">Submit payment</x-mbui.button>
                </div>
            </form>
        @endif
    </div>

</x-layouts.user>