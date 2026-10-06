@php
    $pct = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<x-layouts.finance title="Statutory Returns" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Statutory returns</h1>
            <p class="mt-1 text-sm text-gray-500">PAYE, SDL, NSSF and WCF owed from each payroll month, when they are due, and whether they have been paid.</p>
        </div>
        <a href="{{ route('admin.finance.payroll.index') }}" class="mbui-anchor text-sm">Go to payroll</a>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Overdue</p>
            <p class="mt-2 text-2xl font-bold {{ $summary['overdue'] > 0 ? 'text-red-600' : 'text-gray-900' }}">TZS {{ number_format($summary['overdue']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Past due date, not yet paid</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Coming up</p>
            <p class="mt-2 text-2xl font-bold text-amber-600">TZS {{ number_format($summary['pending']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Pending, not yet due</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Paid in {{ now()->year }}</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">TZS {{ number_format($summary['paid_this_year']) }}</p>
            <p class="mt-1 text-xs text-gray-500">All authorities</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Provisions built up</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">TZS {{ number_format($summary['provisions']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Leave {{ number_format($summary['leave_provision']) }} · Severance {{ number_format($summary['severance_provision']) }} · Gratuity {{ number_format($summary['gratuity_provision']) }}</p>
        </x-mbui.card>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Return</th>
                    <th class="mbui-th">Payroll month</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Due</th>
                    <th class="mbui-th">Status</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($returns as $return)
                    <tr>
                        <td class="mbui-td">
                            <p class="font-semibold text-gray-900">{{ $return->label() }}</p>
                            <p class="text-xs text-gray-500">Pay to {{ $return->authority }}</p>
                        </td>
                        <td class="mbui-td">{{ $return->payrollPeriod?->name ?? '-' }}</td>
                        <td class="mbui-td font-semibold text-gray-900">TZS {{ number_format((float) $return->amount) }}</td>
                        <td class="mbui-td {{ $return->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">{{ $return->due_date->format('d M Y') }}</td>
                        <td class="mbui-td">
                            @if ($return->isPaid())
                                <x-mbui.badge appearance="success">Paid {{ $return->paid_at?->format('d M') }}</x-mbui.badge>
                                @if ($return->reference)
                                    <p class="mt-1 font-mono text-xs text-gray-500">{{ $return->reference }}</p>
                                @endif
                            @elseif ($return->isOverdue())
                                <x-mbui.badge appearance="danger">Overdue</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="warning">Pending</x-mbui.badge>
                            @endif
                        </td>
                        <td class="mbui-td">
                            @if ($return->isPaid())
                                <form method="POST" action="{{ route('admin.finance.returns.pending', $return) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Undo</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.finance.returns.paid', $return) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="date" name="paid_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="mbui-input w-36 py-1 text-xs" aria-label="Date paid">
                                    <input type="text" name="reference" placeholder="Control no. / receipt" class="mbui-input w-40 py-1 text-xs" aria-label="Payment reference">
                                    <x-mbui.button type="submit">Mark paid</x-mbui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No returns yet. Prepare a payroll draft and its PAYE, SDL, NSSF and WCF will appear here.</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $returns->links() }}</div>

    <x-mbui.card class="mt-6 p-6">
        <h2 class="text-base font-semibold text-gray-900">What each one means</h2>
        <dl class="mt-4 grid gap-5 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-semibold text-gray-900">PAYE (Pay As You Earn), paid to TRA</dt>
                <dd class="mt-1 text-gray-600">Income tax taken from each staff member's pay, on pay after their NSSF contribution, using the TRA monthly bands (0% up to TZS 270,000, rising to 30% above TZS 1,000,000). Paid by the 7th of the next month.</dd>
            </div>
            <div>
                <dt class="font-semibold text-gray-900">SDL (Skills Development Levy), paid to TRA</dt>
                <dd class="mt-1 text-gray-600">{{ $pct($rates['sdl']) }}% of total gross pay, paid by the company (not deducted from staff). Only applies once the company has {{ (int) $rates['sdl_min_staff'] }} or more staff. Paid with PAYE by the 7th of the next month.</dd>
            </div>
            <div>
                <dt class="font-semibold text-gray-900">NSSF (National Social Security Fund)</dt>
                <dd class="mt-1 text-gray-600">Pension contribution on gross pay: {{ $pct($rates['nssf_employee']) }}% deducted from the staff member plus {{ $pct($rates['nssf_employer']) }}% added by the company. Both parts are paid together by the end of the next month.</dd>
            </div>
            <div>
                <dt class="font-semibold text-gray-900">WCF (Workers Compensation Fund)</dt>
                <dd class="mt-1 text-gray-600">{{ $pct($rates['wcf']) }}% of gross pay, paid by the company to insure staff against work injuries. Paid by the end of the next month.</dd>
            </div>
            <div>
                <dt class="font-semibold text-gray-900">Provisions</dt>
                <dd class="mt-1 text-gray-600">Money set aside each month for costs the company will owe later: annual leave pay ({{ $pct($rates['leave_provision']) }}% of basic), severance ({{ $pct($rates['severance_provision']) }}% of basic) and contract gratuity ({{ $pct($rates['gratuity_provision']) }}% of basic). Not paid to anyone monthly; it is a reserve.</dd>
            </div>
            <div>
                <dt class="font-semibold text-gray-900">Half-year returns</dt>
                <dd class="mt-1 text-gray-600">Besides the monthly payments, TRA expects half-year PAYE and SDL returns (for January–June and July–December), filed within 30 days after each half ends. Use the paid PAYE and SDL rows above as the totals.</dd>
            </div>
        </dl>
        <p class="mt-5 text-xs text-gray-500">Rates and due dates follow current Tanzania mainland rules as configured. Confirm with TRA, NSSF, WCF or your accountant before filing; change rates under Payroll &rarr; Statutory settings.</p>
    </x-mbui.card>

</x-layouts.finance>
