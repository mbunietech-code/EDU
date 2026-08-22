<x-layouts.user :title="$tool->name" :header="$tool->name">

    <nav class="text-sm text-gray-500">
        <a href="{{ route('user.tools.index') }}" class="hover:text-gray-900">Research Tools</a>
        <span class="mx-1">/</span>
        <span class="text-gray-900">{{ $tool->name }}</span>
    </nav>

    <div class="mt-6 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="flex items-start justify-between">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $tool->name }}</h1>
                @if ($tool->is_featured)
                    <x-mbui.badge appearance="warning">Featured</x-mbui.badge>
                @endif
            </div>
            @if ($tool->version)
                <p class="mt-1 text-sm text-gray-500">Version {{ $tool->version }}</p>
            @endif
            <div class="mt-4 prose prose-gray max-w-none">
                <p class="text-gray-600">{{ $tool->description }}</p>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="mbui-card p-6 sticky top-24">
                <h2 class="text-lg font-semibold text-gray-900">Get access</h2>
                <p class="mt-1 text-sm text-gray-500">A product key is sent to you automatically once your payment is approved.</p>
                <div class="mt-6 rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-900">One-time price</span>
                        <span class="text-sm font-bold text-gray-900">TZS {{ number_format($tool->price) }}</span>
                    </div>
                    <div class="mt-1 text-right">
                        <x-currency-conversion :amount="$tool->price" class="text-xs font-semibold text-gray-600" />
                    </div>
                    <a href="{{ route('user.tool-orders.create', $tool) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        Order now
                    </a>
                </div>
            </div>
        </div>
    </div>

</x-layouts.user>
