<x-layouts.finance title="Staff Dashboard" header="Finance">
    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Staff & Payroll</h1>
            <p class="mt-1 text-sm text-gray-500">Staff count, contracts and payroll readiness inside Finance.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.staff.index') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Add staff</a>
            <a href="{{ route('admin.finance.payroll.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Prepare payroll</a>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Total Staff</p><p class="mt-2 text-2xl font-bold">{{ number_format($metrics['total_staff']) }}</p></x-mbui.card>
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Active Staff</p><p class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($metrics['active_staff']) }}</p></x-mbui.card>
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Active Contracts</p><p class="mt-2 text-2xl font-bold">{{ number_format($metrics['active_contracts']) }}</p></x-mbui.card>
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Expiring in 30 days</p><p class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($metrics['expiring_contracts']) }}</p></x-mbui.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="mbui-card overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-900">Contracts needing attention</h2>
            </div>
            <x-mbui.table>
                <thead><tr class="border-b bg-gray-50"><th class="mbui-th">Staff</th><th class="mbui-th">Contract</th><th class="mbui-th">Ends</th><th class="mbui-th">Status</th></tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($expiringContracts as $contract)
                        <tr>
                            <td class="mbui-td font-medium text-gray-900">{{ $contract->staff?->fullName() }}</td>
                            <td class="mbui-td">{{ $contract->contract_number }}</td>
                            <td class="mbui-td">{{ $contract->end_date?->format('d M Y') }}</td>
                            <td class="mbui-td"><x-mbui.badge appearance="warning">{{ ucfirst($contract->status) }}</x-mbui.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="mbui-td text-center text-gray-400">No contracts expiring soon</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>
        </div>

        <div class="space-y-6">
            <x-mbui.card class="p-5">
                <p class="text-sm text-gray-500">Latest Payroll Net</p>
                <p class="mt-2 text-2xl font-bold">TZS {{ number_format($metrics['current_payroll_net']) }}</p>
                <p class="mt-1 text-xs text-gray-400">Draft payroll currently sums active staff basic salaries only.</p>
            </x-mbui.card>
            <x-mbui.card class="p-5">
                <h2 class="text-base font-semibold text-gray-900">Staff by rank</h2>
                <p class="mt-1 text-xs text-gray-400">Most senior position first.</p>
                <div class="mt-3 space-y-3">
                    @forelse ($recentStaff as $person)
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $person->fullName() }}</p>
                                <p class="text-xs text-gray-500">{{ $person->department?->name ?? 'No department' }} · {{ $person->position?->name ?? 'No position' }}</p>
                            </div>
                            <x-mbui.badge appearance="neutral">{{ str_replace('_', ' ', ucfirst($person->status)) }}</x-mbui.badge>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No staff recorded yet.</p>
                    @endforelse
                </div>
            </x-mbui.card>
        </div>
    </div>
</x-layouts.finance>
