<x-layouts.admin title="Company assets" header="Company">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Company assets</h1>
            <p class="mt-1 text-sm text-gray-500">Everything Mbunie pays for — domains, hosting, servers, software, licences, devices — when it was bought, when it ends and where to renew it.</p>
        </div>
        @can('assets.manage')
            <x-mbui.btn-link :href="route('admin.assets.create')">+ Add asset</x-mbui.btn-link>
        @endcan
    </div>

    {{-- Summary --}}
    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="mbui-card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">All assets</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($counts['all']) }}</p>
        </div>
        <a href="{{ route('admin.assets.index', ['status' => 'soon']) }}" class="mbui-card block p-4 hover:ring-2 hover:ring-amber-200">
            <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Ending in {{ \App\Models\CompanyAsset::SOON_DAYS }} days</p>
            <p class="mt-1 text-2xl font-semibold text-amber-700">{{ number_format($counts['soon']) }}</p>
        </a>
        <a href="{{ route('admin.assets.index', ['status' => 'expired']) }}" class="mbui-card block p-4 hover:ring-2 hover:ring-red-200">
            <p class="text-xs font-medium uppercase tracking-wide text-red-700">Ended</p>
            <p class="mt-1 text-2xl font-semibold text-red-700">{{ number_format($counts['expired']) }}</p>
        </a>
        <div class="mbui-card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Cost per year</p>
            @forelse ($yearly as $currency => $sum)
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ $currency }} {{ number_format($sum, $sum < 1000 ? 2 : 0) }}</p>
            @empty
                <p class="mt-1 text-2xl font-semibold text-gray-400">—</p>
            @endforelse
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.assets.index') }}" class="mt-6 flex flex-col gap-2 lg:flex-row lg:items-center">
        <div class="flex flex-wrap gap-1">
            @foreach (['all' => 'All', 'attention' => 'Needs attention', 'expired' => 'Ended', 'soon' => 'Ending soon', 'active' => 'Active', 'no_expiry' => 'Never ends'] as $key => $label)
                <a href="{{ route('admin.assets.index', array_filter(['status' => $key === 'all' ? null : $key, 'category' => $category ?: null, 'q' => $search ?: null])) }}"
                    class="rounded-full px-3 py-1.5 text-sm font-medium {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex flex-1 flex-wrap gap-2 lg:justify-end">
            @if ($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <select name="category" class="mbui-input w-auto" onchange="this.form.submit()" aria-label="Category">
                <option value="">All categories</option>
                @foreach (\App\Models\CompanyAsset::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name, vendor, account…" class="mbui-input min-w-0 flex-1 lg:max-w-xs" aria-label="Search">
            <x-mbui.button type="submit" variant="secondary">Search</x-mbui.button>
        </div>
    </form>

    {{-- List: one card per asset, wrapping on any screen --}}
    @if ($assets->isEmpty())
        <div class="mbui-card mt-4">
            <x-mbui.empty-state title="No assets here" :message="$counts['all'] === 0 ? 'Add the first domain, hosting plan or software Mbunie pays for.' : 'Nothing matches these filters.'" />
        </div>
    @else
        <ul class="mt-4 space-y-3" role="list">
            @foreach ($assets as $asset)
                @php
                    $state = $asset->state();
                    $days = $asset->daysLeft();
                    $badge = ['expired' => 'danger', 'soon' => 'warning', 'active' => 'success', 'no_expiry' => 'neutral'][$state];
                    $badgeText = match ($state) {
                        'expired' => 'Ended '.abs($days).' day'.(abs($days) === 1 ? '' : 's').' ago',
                        'soon' => $days === 0 ? 'Ends today' : 'Ends in '.$days.' day'.($days === 1 ? '' : 's'),
                        'active' => 'Active',
                        default => 'Never ends',
                    };
                @endphp
                <li class="mbui-card p-4 {{ $state === 'expired' ? 'ring-1 ring-red-200' : ($state === 'soon' ? 'ring-1 ring-amber-200' : '') }}">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start">
                        {{-- What --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-gray-900 [overflow-wrap:anywhere]">{{ $asset->name }}</p>
                                <x-mbui.badge>{{ $asset->categoryLabel() }}</x-mbui.badge>
                                <x-mbui.badge :appearance="$badge">{{ $badgeText }}</x-mbui.badge>
                                @if ($asset->auto_renew)
                                    <x-mbui.badge appearance="info">Auto-renews</x-mbui.badge>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600 [overflow-wrap:anywhere]">
                                @if ($asset->vendor) Bought from <span class="font-medium text-gray-800">{{ $asset->vendor }}</span>@endif
                                @if ($asset->account) · account <span class="font-medium text-gray-800">{{ $asset->account }}</span>@endif
                            </p>
                            @if ($asset->notes)
                                <p class="mt-1 line-clamp-2 text-xs text-gray-500 [overflow-wrap:anywhere]">{{ $asset->notes }}</p>
                            @endif
                        </div>

                        {{-- When / how much / who --}}
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4 lg:w-[30rem] lg:shrink-0">
                            <div>
                                <dt class="text-xs text-gray-500">Bought</dt>
                                <dd class="text-gray-900">{{ $asset->purchased_at?->format('d M Y') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Ends</dt>
                                <dd class="font-medium {{ $state === 'expired' ? 'text-red-700' : ($state === 'soon' ? 'text-amber-700' : 'text-gray-900') }}">{{ $asset->expires_at?->format('d M Y') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Cost</dt>
                                <dd class="text-gray-900">
                                    @if ($asset->cost !== null){{ $asset->currency }} {{ number_format((float) $asset->cost, 2) }}@else — @endif
                                    <span class="block text-xs text-gray-500">{{ $asset->cycleLabel() }}</span>
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500">Looks after it</dt>
                                <dd class="truncate text-gray-900">{{ $asset->responsible?->name ?? '—' }}
                                    @if ($asset->responsible?->job_title)<span class="block truncate text-xs text-gray-500">{{ $asset->responsible->job_title }}</span>@endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                        @if ($asset->purchase_url)
                            <a href="{{ $asset->purchase_url }}" target="_blank" rel="noopener noreferrer"
                                class="rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50">Open where to buy ↗</a>
                        @endif
                        @can('assets.manage')
                            @if ($asset->billing_cycle !== 'one_time')
                                <form method="POST" action="{{ route('admin.assets.renew', $asset) }}"
                                    onsubmit="return confirm(@js('Mark '.$asset->name.' as renewed today? Its end date moves on by one '.strtolower($asset->cycleLabel()).' period.'))">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-500">Renewed</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.assets.edit', $asset) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit</a>
                            <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}" class="ml-auto"
                                onsubmit="return confirm(@js('Delete '.$asset->name.'?'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
</x-layouts.admin>
