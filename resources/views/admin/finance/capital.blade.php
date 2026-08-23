<x-layouts.finance title="Capital" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Capital</h1>
            <p class="mt-1 text-sm text-gray-500">Money put into each software, and where it came from.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card p-6">
        <h2 class="text-base font-semibold text-gray-900">Add capital entry</h2>
        <form method="POST" action="{{ route('admin.finance.capital.store') }}" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="cap-label" value="Software / label" />
                    <x-text-input id="cap-label" class="mbui-input mt-1" type="text" name="label" :value="old('label')" placeholder="e.g. Grammarly" required />
                    <x-input-error :messages="$errors->get('label')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cap-product" value="Link to product (optional)" />
                    <select id="cap-product" name="product_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="cap-tool" value="Link to tool (optional)" />
                    <select id="cap-tool" name="tool_id" class="mbui-input mt-1">
                        <option value="">Not linked</option>
                        @foreach ($tools as $tool)
                            <option value="{{ $tool->id }}" @selected(old('tool_id') == $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="cap-amount" value="Amount (TZS)" />
                    <x-text-input id="cap-amount" class="mbui-input mt-1" type="number" step="0.01" min="0" name="amount" :value="old('amount')" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cap-source" value="Source of this money" />
                    <x-text-input id="cap-source" class="mbui-input mt-1" type="text" name="source" :value="old('source')" placeholder="e.g. Owner savings, CRDB loan" required />
                    <x-input-error :messages="$errors->get('source')" class="mt-2" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="is_loan" value="0">
                <input type="checkbox" name="is_loan" value="1" @checked(old('is_loan')) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                This is a loan and needs to be repaid
            </label>
            <div>
                <x-input-label for="cap-notes" value="Notes (optional)" />
                <textarea id="cap-notes" name="notes" rows="2" class="mbui-input mt-1">{{ old('notes') }}</textarea>
            </div>
            <div class="flex justify-end border-t border-gray-100 pt-4">
                <x-mbui.button type="submit">Add capital</x-mbui.button>
            </div>
        </form>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Software</th>
                    <th class="mbui-th">Amount</th>
                    <th class="mbui-th">Source</th>
                    <th class="mbui-th">Loan?</th>
                    <th class="mbui-th">Recorded</th>
                    <th class="mbui-th">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="mbui-td">
                            <p class="font-medium text-gray-900">{{ $entry->label }}</p>
                            @if ($entry->notes)
                                <p class="text-xs text-gray-500">{{ $entry->notes }}</p>
                            @endif
                        </td>
                        <td class="mbui-td font-semibold text-gray-900">TZS {{ number_format($entry->amount) }}</td>
                        <td class="mbui-td">{{ $entry->source }}</td>
                        <td class="mbui-td">
                            @if ($entry->is_loan)
                                <x-mbui.badge appearance="warning">Loan — repay</x-mbui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="mbui-td text-gray-500">{{ $entry->created_at->format('d M Y') }} @if($entry->creator) &middot; {{ $entry->creator->name }} @endif</td>
                        <td class="mbui-td">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.finance.capital.edit', $entry) }}" class="mbui-anchor text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.finance.capital.destroy', $entry) }}" onsubmit="return confirm('Remove this capital entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No capital recorded yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $entries->links() }}</div>

</x-layouts.finance>
