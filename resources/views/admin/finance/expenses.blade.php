<x-layouts.finance title="Expenses" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Expenses</h1>
            <p class="mt-1 text-sm text-gray-500">Money spent on each software.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card p-6">
        <h2 class="text-base font-semibold text-gray-900">Add expense</h2>
        <form method="POST" action="{{ route('admin.finance.expenses.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="exp-label" value="Software / label" />
                    <x-text-input id="exp-label" class="mbui-input mt-1" type="text" name="label" :value="old('label')" placeholder="e.g. Grammarly" required />
                    <x-input-error :messages="$errors->get('label')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="exp-product" value="Link to product (optional)" />
                    <select id="exp-product" name="product_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="exp-tool" value="Link to tool (optional)" />
                    <select id="exp-tool" name="tool_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($tools as $tool)
                            <option value="{{ $tool->id }}" @selected(old('tool_id') == $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="exp-amount" value="Amount (TZS)" />
                    <x-text-input id="exp-amount" class="mbui-input mt-1" type="number" step="0.01" min="0" name="amount" :value="old('amount')" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="exp-category" value="Category" />
                    <select id="exp-category" name="category" class="mbui-input mt-1">
                        <option value="">Select category</option>
                        <option value="Operating Expenses (OPEX)" @selected(old('category') === 'Operating Expenses (OPEX)')>Operating Expenses (OPEX) — rent, salaries, hosting, marketing</option>
                        <option value="Cost of Goods Sold (COGS)" @selected(old('category') === 'Cost of Goods Sold (COGS)')>Cost of Goods Sold (COGS) — direct product/account costs</option>
                        <option value="Financial Expenses" @selected(old('category') === 'Financial Expenses')>Financial Expenses — loan interest, bank charges</option>
                        <option value="Depreciation & Amortization" @selected(old('category') === 'Depreciation & Amortization')>Depreciation & Amortization</option>
                        <option value="Miscellaneous" @selected(old('category') === 'Miscellaneous')>Miscellaneous</option>
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="exp-date" value="Date spent" />
                    <x-text-input id="exp-date" class="mbui-input mt-1" type="date" name="spent_at" :value="old('spent_at', now()->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('spent_at')" class="mt-2" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="exp-description" value="Description (optional)" />
                    <textarea id="exp-description" name="description" rows="2" class="mbui-input mt-1">{{ old('description') }}</textarea>
                </div>
                <div>
                    <x-input-label for="exp-receipt" value="Receipt (optional)" />
                    @if (($receiptsSupported ?? true))
                        <input id="exp-receipt" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                            class="mbui-input mt-1 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700" />
                        <p class="mt-1 text-xs text-gray-500">Image or PDF, max 5 MB.</p>
                        <x-input-error :messages="$errors->get('receipt')" class="mt-2" />
                    @else
                        <p class="mt-1 text-xs text-amber-600">Receipt uploads need a database update — see Admin → Database.</p>
                    @endif
                </div>
            </div>
            <div class="flex justify-end border-t border-gray-100 pt-4">
                <x-mbui.button type="submit">Add expense</x-mbui.button>
            </div>
        </form>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Software</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Category</th>
                    <th class="mbui-th">Date</th>
                    <th class="mbui-th">Receipt</th>
                    <th class="mbui-th">Recorded by</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="mbui-td">
                            <p class="font-medium text-gray-900">{{ $expense->label }}</p>
                            @if ($expense->description)
                                <p class="text-xs text-gray-500">{{ $expense->description }}</p>
                            @endif
                        </td>
                        <td class="mbui-td font-semibold text-red-600">TZS {{ number_format($expense->amount) }}</td>
                        <td class="mbui-td">{{ $expense->category ?? '-' }}</td>
                        <td class="mbui-td text-gray-500">{{ $expense->spent_at->format('d M Y') }}</td>
                        <td class="mbui-td">
                            @if ($expense->hasReceipt())
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.finance.expenses.receipt', $expense) }}" target="_blank" rel="noopener" class="mbui-anchor text-sm">View</a>
                                    <a href="{{ route('admin.finance.expenses.receipt', ['expense' => $expense, 'download' => 1]) }}" class="mbui-anchor text-sm">Download</a>
                                </div>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="mbui-td text-gray-500">{{ $expense->creator?->name ?? '-' }}</td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <x-mbui.icon-link :href="route('admin.finance.expenses.edit', $expense)" />
                                <x-mbui.reasoned-action :action="route('admin.finance.expenses.destroy', $expense)" method="DELETE" label="Remove" prompt-text="Why are you removing this expense?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mbui-td text-center text-gray-400">No expenses recorded yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $expenses->links() }}</div>

</x-layouts.finance>
