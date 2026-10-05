<x-layouts.finance title="Payroll" header="Finance">
    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payroll</h1>
            <p class="mt-1 text-sm text-gray-500">Prepare draft payroll periods from active staff basic salaries.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-3">
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Active Staff</p><p class="mt-2 text-2xl font-bold">{{ number_format($activeStaffCount) }}</p></x-mbui.card>
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Current Gross Base</p><p class="mt-2 text-2xl font-bold">TZS {{ number_format($activeSalaryTotal) }}</p></x-mbui.card>
        <x-mbui.card class="p-5"><p class="text-sm text-gray-500">Deductions</p><p class="mt-2 text-2xl font-bold text-gray-400">Configurable next</p></x-mbui.card>
    </div>

    <x-mbui.card class="mt-6 p-6">
        <h2 class="text-base font-semibold text-gray-900">Prepare payroll period</h2>
        <form method="POST" action="{{ route('admin.finance.payroll.store') }}" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
            @csrf
            <div>
                <x-input-label for="period_month" value="Payroll month" />
                <input id="period_month" name="period_month" type="month" required class="mbui-input mt-1">
            </div>
            <x-mbui.button type="submit">Prepare draft</x-mbui.button>
        </form>
        <p class="mt-3 text-xs text-gray-500">This first finance payroll layer snapshots active staff count and basic salary totals. Earnings, deductions and payslip PDFs can be layered next.</p>
    </x-mbui.card>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead><tr class="border-b bg-gray-50"><th class="mbui-th">Period</th><th class="mbui-th">Staff</th><th class="mbui-th">Gross</th><th class="mbui-th">Deductions</th><th class="mbui-th">Net</th><th class="mbui-th">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($payrolls as $payroll)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $payroll->name }}</td>
                        <td class="mbui-td">{{ number_format($payroll->staff_count) }}</td>
                        <td class="mbui-td">TZS {{ number_format((float) $payroll->gross_pay) }}</td>
                        <td class="mbui-td text-red-600">TZS {{ number_format((float) $payroll->total_deductions) }}</td>
                        <td class="mbui-td font-semibold">TZS {{ number_format((float) $payroll->net_pay) }}</td>
                        <td class="mbui-td"><x-mbui.badge appearance="neutral">{{ ucfirst($payroll->status) }}</x-mbui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No payroll periods prepared yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $payrolls->links() }}</div>
</x-layouts.finance>
