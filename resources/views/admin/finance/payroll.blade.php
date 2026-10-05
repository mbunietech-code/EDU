<x-layouts.finance title="Payroll" header="Finance">
    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payroll</h1>
            <p class="mt-1 text-sm text-gray-500">Prepare payroll with earnings, allowances, statutory deductions, loans and staff-level net pay.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Active Staff</p>
            <p class="mt-2 text-2xl font-bold">{{ number_format($activeStaffCount) }}</p>
            <p class="mt-1 text-xs text-gray-500">Staff included in next draft</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Basic Salary Base</p>
            <p class="mt-2 text-2xl font-bold">TZS {{ number_format($activeSalaryTotal) }}</p>
            <p class="mt-1 text-xs text-gray-500">Before allowances and deductions</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Latest Net Payroll</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700">TZS {{ number_format($payrollTotals['latest_net']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $latestPayroll?->name ?? 'No period prepared yet' }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm text-gray-500">Latest Deductions</p>
            <p class="mt-2 text-2xl font-bold text-red-600">TZS {{ number_format($payrollTotals['latest_deductions']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Tax, pension, insurance, loans, other</p>
        </x-mbui.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-mbui.card class="p-6 xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Prepare payroll draft</h2>
                    <p class="mt-1 text-sm text-gray-500">Amounts below are applied per active staff member for this draft. You can regenerate the same month while it is still draft.</p>
                </div>
                <x-mbui.badge appearance="neutral">{{ number_format($payrollTotals['drafts']) }} draft</x-mbui.badge>
            </div>

            <form method="POST" action="{{ route('admin.finance.payroll.store') }}" class="mt-5 space-y-6">
                @csrf

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="period_month" value="Payroll month" />
                        <input id="period_month" name="period_month" type="month" required class="mbui-input mt-1" value="{{ old('period_month', now()->format('Y-m')) }}">
                        <x-input-error :messages="$errors->get('period_month')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tax_rate" value="Tax rate %" />
                        <input id="tax_rate" name="tax_rate" type="number" min="0" max="100" step="0.01" class="mbui-input mt-1" value="{{ old('tax_rate', 0) }}">
                    </div>
                    <div>
                        <x-input-label for="pension_rate" value="Pension rate %" />
                        <input id="pension_rate" name="pension_rate" type="number" min="0" max="100" step="0.01" class="mbui-input mt-1" value="{{ old('pension_rate', 0) }}">
                    </div>
                </div>

                <div>
                    <h3 class="mbui-section-label">Earnings per staff</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <x-input-label for="transport_allowance" value="Transport" />
                            <input id="transport_allowance" name="transport_allowance" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('transport_allowance', 0) }}">
                        </div>
                        <div>
                            <x-input-label for="meal_allowance" value="Meal" />
                            <input id="meal_allowance" name="meal_allowance" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('meal_allowance', 0) }}">
                        </div>
                        <div>
                            <x-input-label for="housing_allowance" value="Housing" />
                            <input id="housing_allowance" name="housing_allowance" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('housing_allowance', 0) }}">
                        </div>
                        <div>
                            <x-input-label for="overtime_pay" value="Overtime" />
                            <input id="overtime_pay" name="overtime_pay" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('overtime_pay', 0) }}">
                        </div>
                        <div>
                            <x-input-label for="bonus_pay" value="Bonus" />
                            <input id="bonus_pay" name="bonus_pay" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('bonus_pay', 0) }}">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="mbui-section-label">Deductions per staff</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="insurance_amount" value="Insurance" />
                            <input id="insurance_amount" name="insurance_amount" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('insurance_amount', 0) }}">
                        </div>
                        <div>
                            <x-input-label for="loan_deductions" value="Loan deduction" />
                            <input id="loan_deductions" name="loan_deductions" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('loan_deductions', 0) }}">
                        </div>
                        <div>
                            <x-input-label for="other_deductions" value="Other deduction" />
                            <input id="other_deductions" name="other_deductions" type="number" min="0" step="0.01" class="mbui-input mt-1" value="{{ old('other_deductions', 0) }}">
                        </div>
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" value="Payroll notes" />
                    <textarea id="notes" name="notes" rows="3" class="mbui-input mt-1" placeholder="Approval notes, payroll changes...">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-5">
                    <p class="text-xs text-gray-500">Draft snapshots every active staff member with salary, payment account, deductions and net pay.</p>
                    <x-mbui.button type="submit">Prepare detailed draft</x-mbui.button>
                </div>
            </form>
        </x-mbui.card>

        <x-mbui.card class="p-6">
            <h2 class="text-base font-semibold text-gray-900">Staff ready for payroll</h2>
            <p class="mt-1 text-sm text-gray-500">First 12 active staff included in payroll snapshots.</p>
            <div class="mt-4 space-y-3">
                @forelse ($activeStaff as $staff)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $staff->fullName() }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $staff->department?->name ?? 'No department' }} · {{ $staff->position?->name ?? 'No position' }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-gray-900">TZS {{ number_format((float) $staff->basic_salary) }}</p>
                    </div>
                @empty
                    <x-mbui.empty-state title="No active staff" message="Add staff before preparing payroll." />
                @endforelse
            </div>
        </x-mbui.card>
    </div>

    @if ($latestPayroll)
        <x-mbui.card class="mt-6 p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Latest payroll breakdown · {{ $latestPayroll->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Staff net pay preview from the newest prepared period.</p>
                </div>
                <x-mbui.badge appearance="neutral">{{ ucfirst($latestPayroll->status) }}</x-mbui.badge>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Basic</p><p class="mt-1 font-bold">TZS {{ number_format((float) $latestPayroll->basic_pay) }}</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Allowances</p><p class="mt-1 font-bold">TZS {{ number_format((float) $latestPayroll->total_allowances) }}</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Gross</p><p class="mt-1 font-bold">TZS {{ number_format((float) $latestPayroll->gross_pay) }}</p></div>
                <div class="rounded-lg bg-red-50 p-3"><p class="text-xs text-red-600">Deductions</p><p class="mt-1 font-bold text-red-700">TZS {{ number_format((float) $latestPayroll->total_deductions) }}</p></div>
                <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Net</p><p class="mt-1 font-bold text-emerald-800">TZS {{ number_format((float) $latestPayroll->net_pay) }}</p></div>
                <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Staff</p><p class="mt-1 font-bold">{{ number_format($latestPayroll->staff_count) }}</p></div>
            </div>

            <div class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="mbui-th">Staff</th>
                            <th class="mbui-th">Gross</th>
                            <th class="mbui-th">Deductions</th>
                            <th class="mbui-th">Net Pay</th>
                            <th class="mbui-th">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($latestPayroll->items as $item)
                            <tr>
                                <td class="mbui-td">
                                    <p class="font-medium text-gray-900">{{ $item->staff_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $item->department_name ?? 'No department' }} · {{ $item->position_name ?? 'No position' }}</p>
                                </td>
                                <td class="mbui-td">TZS {{ number_format((float) $item->gross_pay) }}</td>
                                <td class="mbui-td text-red-600">TZS {{ number_format((float) $item->total_deductions) }}</td>
                                <td class="mbui-td font-semibold text-emerald-700">TZS {{ number_format((float) $item->net_pay) }}</td>
                                <td class="mbui-td">{{ $item->payment_channel ? ucfirst(str_replace('_', ' ', $item->payment_channel)) : 'Not set' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-mbui.card>
    @endif

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="border-b bg-gray-50">
                    <th class="mbui-th">Period</th>
                    <th class="mbui-th">Staff</th>
                    <th class="mbui-th">Basic</th>
                    <th class="mbui-th">Allowances</th>
                    <th class="mbui-th">Gross</th>
                    <th class="mbui-th">Deductions</th>
                    <th class="mbui-th">Net</th>
                    <th class="mbui-th">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($payrolls as $payroll)
                    <tr>
                        <td class="mbui-td">
                            <p class="font-medium text-gray-900">{{ $payroll->name }}</p>
                            <p class="text-xs text-gray-500">{{ $payroll->prepared_at?->format('d M Y H:i') ?? 'Not prepared' }}</p>
                        </td>
                        <td class="mbui-td">{{ number_format($payroll->staff_count) }} <span class="text-xs text-gray-400">({{ number_format($payroll->items_count) }} lines)</span></td>
                        <td class="mbui-td">TZS {{ number_format((float) ($payroll->basic_pay ?: $payroll->gross_pay)) }}</td>
                        <td class="mbui-td">TZS {{ number_format((float) $payroll->total_allowances) }}</td>
                        <td class="mbui-td">TZS {{ number_format((float) $payroll->gross_pay) }}</td>
                        <td class="mbui-td text-red-600">TZS {{ number_format((float) $payroll->total_deductions) }}</td>
                        <td class="mbui-td font-semibold text-emerald-700">TZS {{ number_format((float) $payroll->net_pay) }}</td>
                        <td class="mbui-td"><x-mbui.badge appearance="neutral">{{ ucfirst($payroll->status) }}</x-mbui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="mbui-td text-center text-gray-400">No payroll periods prepared yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $payrolls->links() }}</div>
</x-layouts.finance>
