<x-layouts.admin title="AI plans we bought" header="AI plans we bought">

    @php
        $tabs = [
            'all' => ['All', $total, 'gray'],
            'expiring' => ['Ending in '.\App\Models\Account::EXPIRING_DAYS.' days', $counts['expiring'] ?? 0, 'amber'],
            'expired' => ['Ended', $counts['expired'] ?? 0, 'red'],
            'active' => ['Running', $counts['active'] ?? 0, 'emerald'],
            'unknown' => ['No end date', $counts['unknown'] ?? 0, 'gray'],
        ];
        $badge = [
            'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'expiring' => 'bg-amber-50 text-amber-800 ring-amber-600/30',
            'expired' => 'bg-red-50 text-red-700 ring-red-600/20',
            'unknown' => 'bg-gray-50 text-gray-600 ring-gray-500/20',
        ];
        $bar = ['active' => 'bg-emerald-500', 'expiring' => 'bg-amber-500', 'expired' => 'bg-red-500', 'unknown' => 'bg-gray-300'];
    @endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">AI plans we bought</h1>
            <p class="mt-1 text-sm text-gray-500">The plans MbunieEduHub pays the AI providers for: when each was bought and when it ends.</p>
        </div>
        @can('accounts.manage')
            <a href="{{ route('admin.accounts.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add a plan</a>
        @endcan
    </div>

    {{-- Summary --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="mbui-card p-5">
            <p class="text-sm text-gray-500">Plans</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $total }}</p>
        </div>
        <div class="mbui-card p-5 {{ ($counts['expiring'] ?? 0) > 0 ? 'ring-2 ring-amber-300' : '' }}">
            <p class="text-sm text-gray-500">Ending in {{ \App\Models\Account::EXPIRING_DAYS }} days</p>
            <p class="mt-1 text-2xl font-bold {{ ($counts['expiring'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $counts['expiring'] ?? 0 }}</p>
        </div>
        <div class="mbui-card p-5 {{ ($counts['expired'] ?? 0) > 0 ? 'ring-2 ring-red-200' : '' }}">
            <p class="text-sm text-gray-500">Already ended</p>
            <p class="mt-1 text-2xl font-bold {{ ($counts['expired'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $counts['expired'] ?? 0 }}</p>
        </div>
        <div class="mbui-card p-5">
            <p class="text-sm text-gray-500">Paid for these plans</p>
            <p class="mt-1 text-lg font-bold text-gray-900">
                @forelse ($spent as $cur => $sum)
                    <span class="block">{{ $cur }} {{ number_format($sum, $cur === 'TZS' ? 0 : 2) }}</span>
                @empty
                    <span class="text-gray-400">—</span>
                @endforelse
            </p>
        </div>
    </div>

    {{-- Filter tabs --}}
    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Filter plans">
        @foreach ($tabs as $key => [$label, $count, $tone])
            <a href="{{ route('admin.accounts.plans', $key === 'all' ? [] : ['filter' => $key]) }}"
               @class([
                   'inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                   'bg-indigo-600 text-white ring-indigo-600' => $filter === $key,
                   'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => $filter !== $key,
               ])>
                {{ $label }}
                <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => $filter === $key, 'bg-gray-100' => $filter !== $key])>{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mt-4 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th">Tool / account</th>
                    <th class="mbui-th">Plan</th>
                    <th class="mbui-th">Bought on</th>
                    <th class="mbui-th">Ends on</th>
                    <th class="mbui-th">Time left</th>
                    <th class="mbui-th">Paid</th>
                    <th class="mbui-th">Customers</th>
                    <th class="mbui-th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($accounts as $account)
                    @php
                        $state = $account->planState();
                        $days = $account->planDaysLeft();
                        $length = ($account->purchased_at && $account->expires_at) ? max(1, $account->purchased_at->diffInDays($account->expires_at)) : null;
                        $used = $length ? min(100, max(0, (int) round(($length - max(0, $days)) / $length * 100))) : null;
                    @endphp
                    <tr>
                        <td class="mbui-td">
                            <a href="{{ route('admin.accounts.show', $account) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $account->product->name ?? '—' }}</a>
                            <p class="text-xs text-gray-500">{{ $account->name }}</p>
                        </td>
                        <td class="mbui-td">
                            {{ $account->plan_name ?: '—' }}
                            @if ($account->auto_renew)
                                <span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700">auto-renew</span>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-nowrap">{{ $account->purchased_at?->format('d M Y') ?? '—' }}</td>
                        <td class="mbui-td whitespace-nowrap font-medium text-gray-900">{{ $account->expires_at?->format('d M Y') ?? '—' }}</td>
                        <td class="mbui-td min-w-[10rem]">
                            <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badge[$state] }}">
                                @switch($state)
                                    @case('expired') Ended {{ abs($days) }} {{ \Illuminate\Support\Str::plural('day', abs($days)) }} ago @break
                                    @case('unknown') No end date @break
                                    @default
                                        @if ($days === 0) Ends today @else {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }} left @endif
                                @endswitch
                            </span>
                            @if ($used !== null)
                                <div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-100" title="{{ $used }}% of the plan used">
                                    <div class="h-1.5 rounded-full {{ $bar[$state] }}" style="width: {{ $used }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="mbui-td whitespace-nowrap">
                            {{ $account->cost !== null ? ($account->cost_currency ?: 'TZS').' '.number_format((float) $account->cost, ($account->cost_currency ?: 'TZS') === 'TZS' ? 0 : 2) : '—' }}
                        </td>
                        <td class="mbui-td">{{ $account->users_count }}</td>
                        <td class="mbui-td">
                            @can('accounts.manage')
                                <x-mbui.icon-link :href="route('admin.accounts.edit', $account)" />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="mbui-td py-10 text-center text-gray-400">
                            No plans here yet. Add the dates on an account (Accounts → edit → "Plan we bought").
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>
