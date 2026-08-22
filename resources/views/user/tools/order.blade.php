<x-layouts.user title="New Order" header="New Order">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $tool->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Review and confirm your order.</p>
        </div>
        <a href="{{ route('user.tools.show', $tool) }}" class="mbui-anchor text-sm">Back to tool</a>
    </div>

    <div class="mt-6 max-w-xl">
        <div class="mbui-card overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-900">Order summary</h2>
            </div>
            <div class="divide-y divide-gray-100">
                <div class="flex items-center justify-between px-6 py-4 text-sm">
                    <span class="text-gray-500">Tool</span>
                    <span class="font-medium text-gray-900">{{ $tool->name }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-4 text-sm">
                    <span class="text-gray-500">Amount</span>
                    <span class="text-lg font-bold text-gray-900">TZS {{ number_format($tool->price) }}</span>
                </div>
                <div class="px-6 pb-4 text-right text-xs text-gray-400">
                    <x-currency-conversion :amount="$tool->price" />
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                <form method="POST" action="{{ route('user.tool-orders.store') }}">
                    @csrf
                    <input type="hidden" name="tool_id" value="{{ $tool->id }}">
                    <x-mbui.button type="submit" class="w-full">Confirm Order</x-mbui.button>
                </form>
            </div>
        </div>

        <div class="mt-4 mbui-card p-4">
            <p class="text-xs text-gray-500">
                After confirming, you will receive payment instructions and a payment proof submission form. Your product key is sent automatically once payment is approved.
            </p>
        </div>
    </div>

</x-layouts.user>
