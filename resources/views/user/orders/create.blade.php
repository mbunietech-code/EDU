<x-layouts.user title="New Order" header="New Order">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Review your selected plan and confirm your order.</p>
        </div>
        <a href="{{ route('public.products.show', $product) }}" class="mbui-anchor text-sm">Back to product</a>
    </div>

    <div class="mt-6 max-w-xl">
        <div class="mbui-card overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-900">Order summary</h2>
            </div>
            <div class="divide-y divide-gray-100">
                <div class="flex items-center justify-between px-6 py-4 text-sm">
                    <span class="text-gray-500">Product</span>
                    <span class="font-medium text-gray-900">{{ $product->name }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-4 text-sm">
                    <span class="text-gray-500">Plan</span>
                    <span class="font-medium text-gray-900">{{ $plan->name }} ({{ $plan->durationLabel() }})</span>
                </div>
                <div class="flex items-center justify-between px-6 py-4 text-sm">
                    <span class="text-gray-500">Plan description</span>
                    <span class="font-medium text-gray-900">{{ $plan->description }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-4 text-sm">
                    <span class="text-gray-500">Amount</span>
                    <span class="text-lg font-bold text-gray-900">TZS {{ number_format($plan->price) }}</span>
                </div>
                <div class="px-6 pb-4 text-right text-xs text-gray-400">
                    <x-currency-conversion :amount="$plan->price" />
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{-- The customer already chose this plan (often before signing in):
                     continue straight to payment. The button stays as a fallback. --}}
                <form method="POST" action="{{ route('user.orders.store') }}" x-data="{ sending: false }"
                    @if (! $errors->any() && ! session('error')) x-init="sending = true; $nextTick(() => $el.submit())" @endif
                    @submit="sending = true">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <x-mbui.button type="submit" class="w-full" x-bind:disabled="sending">
                        <span x-show="! sending">Continue to payment</span>
                        <span x-show="sending">Opening payment...</span>
                    </x-mbui.button>
                </form>
            </div>
        </div>

        <div class="mt-4 mbui-card p-4">
            <p class="text-xs text-gray-500">
                Next you choose how to pay: mobile money, card or manual payment.
            </p>
        </div>
    </div>

</x-layouts.user>