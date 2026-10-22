@props(['selected' => null])

{{-- Expense type (Mwongozo wa Fedha §5). Picking one fills the category and,
     when the label is empty or still the previous type's, a standard label.
     Renders nothing until the expense types database change is applied. --}}
@if (\App\Models\FinanceExpense::typesReady())
    @php($types = \App\Models\FinanceExpense::types())
    <div {{ $attributes }}>
        <x-input-label for="exp-type" value="Type" />
        <select id="exp-type" name="expense_type" class="mbui-input mt-1" data-expense-type>
            <option value="">Choose type…</option>
            @foreach ($types as $key => $t)
                <option value="{{ $key }}" data-category="{{ $t['category'] }}" data-label="{{ $t['default_label'] ?? '' }}" data-product="{{ ! empty($t['product']) ? 1 : 0 }}"
                    @selected(old('expense_type', $selected) === $key)>{{ $t['label'] }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500" data-expense-type-hint></p>
        <x-input-error :messages="$errors->get('expense_type')" class="mt-2" />
    </div>
    <script>
        (function () {
            var select = document.querySelector('[data-expense-type]');
            if (!select) return;
            var form = select.form;
            var label = form.querySelector('[name="label"]');
            var category = form.querySelector('[name="category"]');
            var hint = form.querySelector('[data-expense-type-hint]');
            var lastDefault = (select.selectedOptions[0] || {}).dataset ? select.selectedOptions[0].dataset.label : '';
            select.addEventListener('change', function () {
                var o = select.selectedOptions[0];
                if (!o || !o.value) { hint.textContent = ''; return; }
                if (category && o.dataset.category) category.value = o.dataset.category;
                if (label && o.dataset.label && (label.value === '' || label.value === lastDefault)) label.value = o.dataset.label;
                lastDefault = o.dataset.label || '';
                hint.textContent = o.dataset.product === '1' ? 'Link it to the product below so it counts against that product.' : '';
            });
        })();
    </script>
@endif
