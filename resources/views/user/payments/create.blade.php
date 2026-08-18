<x-layouts.user title="Submit Payment" header="Submit Payment" x-data="{ preview: null }">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Submit payment for {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">Amount due: <span class="font-semibold">TZS {{ number_format($order->amount) }}</span></p>
        </div>
        <a href="{{ route('user.orders.show', $order) }}" class="mbui-anchor text-sm">Back to order</a>
    </div>

    <div class="mt-6 mbui-card p-6">
        <form method="POST" action="{{ route('user.payments.store', $order) }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="payment_method" value="Payment method" />
                    <select id="payment_method" name="payment_method" class="mbui-input mt-1" required>
                        <option value="">Select method</option>
                        <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank transfer</option>
                        <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile money</option>
                    </select>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="transaction_reference" value="Transaction / reference number" />
                    <x-text-input id="transaction_reference" class="mbui-input mt-1" type="text" name="transaction_reference" :value="old('transaction_reference')" required />
                    <x-input-error :messages="$errors->get('transaction_reference')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="amount" value="Amount paid" />
                    <x-text-input id="amount" class="mbui-input mt-1" type="number" step="0.01" min="0" name="amount" :value="old('amount', $order->amount)" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>

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
            </div>

            <div>
                <x-input-label for="note" value="Note (optional)" />
                <textarea id="note" name="note" rows="3" class="mbui-input mt-1" placeholder="Any additional information about this payment">{{ old('note') }}</textarea>
                <x-input-error :messages="$errors->get('note')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('user.orders.show', $order) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Submit payment</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.user>