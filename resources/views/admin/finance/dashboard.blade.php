<x-layouts.finance title="Overview" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Finance Overview</h1>
            <p class="mt-1 text-sm text-gray-500">Capital, income, expenses and balance for each software.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.finance.capital.index') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Add capital</a>
            <a href="{{ route('admin.finance.expenses.index') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add expense</a>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Total Capital</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900">TZS {{ number_format($totals['capital']) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Total Income</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600">TZS {{ number_format($totals['income']) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Total Expenses</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-red-600">TZS {{ number_format($totals['expenses']) }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-6">
            <p class="text-sm font-medium text-gray-500">Balance</p>
            <p class="mt-2 text-2xl font-bold tracking-tight {{ $totals['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($totals['balance']) }}</p>
        </x-mbui.card>
    </div>

    <div class="mt-8 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Per software</h2>
            <p class="mt-1 text-sm text-gray-500">Income is pulled automatically from approved payments. Capital and expenses are recorded manually.</p>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Software</th>
                    <th class="mbui-th">Type</th>
                    <th class="mbui-th">Capital</th>
                    <th class="mbui-th">Income</th>
                    <th class="mbui-th">Expenses</th>
                    <th class="mbui-th">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($rows as $row)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $row['label'] }}</td>
                        <td class="mbui-td"><x-mbui.badge appearance="neutral">{{ $row['type'] }}</x-mbui.badge></td>
                        <td class="mbui-td">TZS {{ number_format($row['capital']) }}</td>
                        <td class="mbui-td text-emerald-600">TZS {{ number_format($row['income']) }}</td>
                        <td class="mbui-td text-red-600">TZS {{ number_format($row['expenses']) }}</td>
                        <td class="mbui-td font-semibold {{ $row['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">TZS {{ number_format($row['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No software tracked yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.finance>
