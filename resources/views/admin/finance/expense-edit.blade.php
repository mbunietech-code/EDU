<x-layouts.finance title="Edit Expense" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Edit expense</h1>
        </div>
        <a href="{{ route('admin.finance.expenses.index') }}" class="mbui-anchor text-sm">Back to expenses</a>
    </div>

    <div class="mt-6 mbui-card p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.finance.expenses.update', $expense) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="exp-label" value="Software / label" />
                    <x-text-input id="exp-label" class="mbui-input mt-1" type="text" name="label" :value="old('label', $expense->label)" required />
                    <x-input-error :messages="$errors->get('label')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="exp-product" value="Link to product (optional)" />
                    <select id="exp-product" name="product_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id', $expense->product_id) == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="exp-tool" value="Link to tool (optional)" />
                    <select id="exp-tool" name="tool_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($tools as $tool)
                            <option value="{{ $tool->id }}" @selected(old('tool_id', $expense->tool_id) == $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="exp-amount" value="Amount (TZS)" />
                    <x-text-input id="exp-amount" class="mbui-input mt-1" type="number" step="0.01" min="0" name="amount" :value="old('amount', $expense->amount)" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="exp-category" value="Category" />
                    <select id="exp-category" name="category" class="mbui-input mt-1">
                        <option value="">Select category</option>
                        @foreach (['Operating Expenses (OPEX)', 'Cost of Goods Sold (COGS)', 'Financial Expenses', 'Depreciation & Amortization', 'Miscellaneous'] as $cat)
                            <option value="{{ $cat }}" @selected(old('category', $expense->category) === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="exp-date" value="Date spent" />
                    <x-text-input id="exp-date" class="mbui-input mt-1" type="date" name="spent_at" :value="old('spent_at', $expense->spent_at->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('spent_at')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="exp-description" value="Description (optional)" />
                <textarea id="exp-description" name="description" rows="2" class="mbui-input mt-1">{{ old('description', $expense->description) }}</textarea>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.finance.expenses.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Update expense</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.finance>
