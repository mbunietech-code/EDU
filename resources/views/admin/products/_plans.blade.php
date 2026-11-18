{{-- Plans edited inside the product form. $planRows from ProductPlanService::formRows(). --}}
<div class="border-t border-gray-100 pt-5"
    x-data="{
        rows: @js($planRows),
        add() { this.rows.push({ id: null, name: '', price: '', duration_type: 'days', duration_days: '30', status: 'active', remove: false }); },
        drop(i) { if (this.rows[i].id) { this.rows[i].remove = true; } else { this.rows.splice(i, 1); } },
    }">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Plans</h2>
            <p class="mt-0.5 text-xs text-gray-500">What customers can buy: name, price and how long it lasts. Saved together with the product.</p>
        </div>
        <button type="button" @click="add()" class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Add plan
        </button>
    </div>

    @if ($errors->has('plans') || $errors->has('plans.*'))
        <p class="mt-2 text-sm text-red-600">{{ $errors->first('plans') ?: collect($errors->get('plans.*'))->flatten()->first() }}</p>
    @endif

    <div class="mt-3 space-y-3">
        <template x-for="(row, i) in rows" :key="i">
            <div>
                <input type="hidden" :name="'plans[' + i + '][id]'" :value="row.id ?? ''">
                <input type="hidden" :name="'plans[' + i + '][remove]'" :value="row.remove ? 1 : 0">

                <div x-show="! row.remove" class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="grid gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <label class="text-xs font-medium text-gray-600" :for="'plan-name-' + i">Plan name</label>
                            <input :id="'plan-name-' + i" type="text" :name="'plans[' + i + '][name]'" x-model="row.name" class="mbui-input mt-1" placeholder="1 month">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="text-xs font-medium text-gray-600" :for="'plan-price-' + i">Price (TZS)</label>
                            <input :id="'plan-price-' + i" type="number" min="0" step="0.01" :name="'plans[' + i + '][price]'" x-model="row.price" class="mbui-input mt-1" placeholder="45000">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="text-xs font-medium text-gray-600" :for="'plan-duration-' + i">Lasts</label>
                            <div class="mt-1 flex gap-2">
                                <select :id="'plan-duration-' + i" :name="'plans[' + i + '][duration_type]'" x-model="row.duration_type" class="mbui-input">
                                    <option value="days">Days</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                                <input type="number" min="1" max="3650" :name="'plans[' + i + '][duration_days]'" x-model="row.duration_days" x-show="row.duration_type === 'days'"
                                    class="mbui-input w-20" aria-label="Number of days">
                            </div>
                        </div>
                        <div class="flex items-end justify-between gap-2 sm:col-span-2">
                            <label class="flex items-center gap-1.5 pb-2 text-xs font-medium text-gray-600">
                                <input type="hidden" :name="'plans[' + i + '][status]'" :value="row.status">
                                <input type="checkbox" :checked="row.status === 'active'" @change="row.status = $event.target.checked ? 'active' : 'inactive'" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                On sale
                            </label>
                            <button type="button" @click="drop(i)" class="mb-1 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove plan">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="row.remove" x-cloak class="flex items-center justify-between rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500">
                    <span><span class="font-semibold" x-text="row.name"></span> will be removed on save (switched off if anyone bought it).</span>
                    <button type="button" @click="row.remove = false" class="font-semibold text-indigo-600 hover:text-indigo-500">Undo</button>
                </div>
            </div>
        </template>

        <p x-show="rows.filter(r => ! r.remove).length === 0" x-cloak class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-sm text-gray-500">
            No plans yet. Customers cannot buy this product until it has a plan.
        </p>
    </div>
</div>
