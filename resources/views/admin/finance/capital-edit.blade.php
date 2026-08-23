<x-layouts.finance title="Edit Capital" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Edit capital entry</h1>
        </div>
        <a href="{{ route('admin.finance.capital.index') }}" class="mbui-anchor text-sm">Back to capital</a>
    </div>

    <div class="mt-6 mbui-card p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.finance.capital.update', $capitalEntry) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="cap-label" value="Software / label" />
                    <x-text-input id="cap-label" class="mbui-input mt-1" type="text" name="label" :value="old('label', $capitalEntry->label)" required />
                    <x-input-error :messages="$errors->get('label')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cap-product" value="Link to product (optional)" />
                    <select id="cap-product" name="product_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id', $capitalEntry->product_id) == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="cap-tool" value="Link to tool (optional)" />
                    <select id="cap-tool" name="tool_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($tools as $tool)
                            <option value="{{ $tool->id }}" @selected(old('tool_id', $capitalEntry->tool_id) == $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="cap-amount" value="Amount (TZS)" />
                    <x-text-input id="cap-amount" class="mbui-input mt-1" type="number" step="0.01" min="0" name="amount" :value="old('amount', $capitalEntry->amount)" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cap-source" value="Source of this money" />
                    <x-text-input id="cap-source" class="mbui-input mt-1" type="text" name="source" :value="old('source', $capitalEntry->source)" required />
                    <x-input-error :messages="$errors->get('source')" class="mt-2" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="is_loan" value="0">
                <input type="checkbox" name="is_loan" value="1" @checked(old('is_loan', $capitalEntry->is_loan)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                This is a loan and needs to be repaid
            </label>
            <div>
                <x-input-label for="cap-notes" value="Notes (optional)" />
                <textarea id="cap-notes" name="notes" rows="2" class="mbui-input mt-1">{{ old('notes', $capitalEntry->notes) }}</textarea>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.finance.capital.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Update capital</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.finance>
