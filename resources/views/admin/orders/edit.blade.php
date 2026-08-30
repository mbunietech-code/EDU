<x-layouts.admin title="Edit Order" header="Edit Order">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Edit {{ $order->order_number }}</h1>
        </div>
        <a href="{{ route('admin.orders.show', $order) }}" class="mbui-anchor text-sm">Back to order</a>
    </div>

    <div class="mt-6 mbui-card p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <x-input-label value="Item" />
                <p class="mt-1 text-sm text-gray-900">{{ $order->itemName() }} &middot; TZS {{ number_format($order->amount) }}</p>
                <p class="mt-1 text-xs text-gray-400">The item, plan and amount aren't editable here since they're tied to the payment already submitted. Use Disapprove or Delete if the order itself is wrong.</p>
            </div>
            <div>
                <x-input-label for="payment_instructions" value="Payment instructions shown to the customer" />
                <textarea id="payment_instructions" name="payment_instructions" rows="4" class="mbui-input mt-1">{{ old('payment_instructions', $order->payment_instructions) }}</textarea>
                <x-input-error :messages="$errors->get('payment_instructions')" class="mt-2" />
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Save changes</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
