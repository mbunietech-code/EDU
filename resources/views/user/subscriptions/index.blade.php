<x-layouts.user title="My Subscriptions" header="My Subscriptions">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">My Subscriptions</h1>
            <p class="mt-1 text-sm text-gray-500">Your active and historical access.</p>
        </div>
        <a href="{{ route('public.products.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">New subscription</a>
    </div>

    <div class="mt-6">
        @if ($subscriptions->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No subscriptions yet" message="Browse AI tools to start your first subscription." />
            </x-mbui.card>
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($subscriptions as $subscription)
                    <div class="mbui-card overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ $subscription->product->name }}</h3>
                                <p class="text-sm text-gray-500">{{ $subscription->plan->name }}</p>
                            </div>
                            <x-mbui.status-badge :status="$subscription->status" />
                        </div>
                        <dl class="grid grid-cols-3 gap-4 px-6 py-5 text-sm">
                            <div>
                                <dt class="mbui-section-label">Start</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $subscription->start_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="mbui-section-label">Expiry</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ $subscription->expiry_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="mbui-section-label">Days left</dt>
                                <dd class="mt-1 font-bold {{ max($subscription->daysRemaining(), 0) <= 3 ? 'text-red-600' : 'text-emerald-600' }}">{{ max($subscription->daysRemaining(), 0) }}</dd>
                            </div>
                        </dl>
                        <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                            <a href="{{ route('user.subscriptions.show', $subscription) }}" class="mbui-anchor text-sm">View details</a>
                            @if ($subscription->isActive())
                                <a href="{{ route('public.products.show', $subscription->product) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Renew</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-8">{{ $subscriptions->links() }}</div>
        @endif
    </div>

</x-layouts.user>