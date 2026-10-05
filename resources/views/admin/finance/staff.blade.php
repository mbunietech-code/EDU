<x-layouts.finance title="Staff" header="Finance">
    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Staff Directory</h1>
            <p class="mt-1 text-sm text-gray-500">Add staff and keep payroll-ready employment details.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <x-mbui.card class="p-6">
            <h2 class="text-base font-semibold text-gray-900">Add staff</h2>
            <form method="POST" action="{{ route('admin.finance.staff.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <div><x-input-label for="first_name" value="First name" /><x-text-input id="first_name" name="first_name" class="mbui-input mt-1" required /></div>
                <div><x-input-label for="last_name" value="Last name" /><x-text-input id="last_name" name="last_name" class="mbui-input mt-1" required /></div>
                <div><x-input-label for="staff_number" value="Staff number" /><x-text-input id="staff_number" name="staff_number" class="mbui-input mt-1" placeholder="Auto: Mhub-001" /></div>
                <div><x-input-label for="phone" value="Phone" /><x-text-input id="phone" name="phone" class="mbui-input mt-1" /></div>
                <div><x-input-label for="email" value="Email" /><x-text-input id="email" type="email" name="email" class="mbui-input mt-1" /></div>
                <div><x-input-label for="hire_date" value="Hire date" /><x-text-input id="hire_date" type="date" name="hire_date" class="mbui-input mt-1" /></div>
                <div>
                    <x-input-label for="finance_department_id" value="Department" />
                    <select id="finance_department_id" name="finance_department_id" class="mbui-input mt-1">
                        <option value="">None</option>
                        @foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="finance_position_id" value="Position" />
                    <select id="finance_position_id" name="finance_position_id" class="mbui-input mt-1">
                        <option value="">None</option>
                        @foreach ($positions as $position)<option value="{{ $position->id }}">{{ $position->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="finance_employment_type_id" value="Employment type" />
                    <select id="finance_employment_type_id" name="finance_employment_type_id" class="mbui-input mt-1">
                        <option value="">None</option>
                        @foreach ($employmentTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mbui-input mt-1">
                        @foreach (['active' => 'Active', 'on_leave' => 'On Leave', 'suspended' => 'Suspended', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'retired' => 'Retired', 'inactive' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><x-input-label for="basic_salary" value="Basic salary" /><x-text-input id="basic_salary" type="number" min="0" step="0.01" name="basic_salary" class="mbui-input mt-1" /></div>
                <div x-data="{ bank: @js(old('bank_name', '')) }">
                    <x-input-label for="bank_name" value="Bank name" />
                    <select id="bank_name" name="bank_name" x-model="bank" class="mbui-input mt-1">
                        <option value="">None</option>
                        @foreach (config('finance.banks') as $bank)<option value="{{ $bank }}">{{ $bank }}</option>@endforeach
                        <option value="other">Other (type name)</option>
                    </select>
                    <x-text-input x-show="bank === 'other'" x-cloak x-bind:required="bank === 'other'" name="bank_name_other" :value="old('bank_name_other')" class="mbui-input mt-2" placeholder="Bank name" />
                    <x-input-error :messages="$errors->get('bank_name_other')" class="mt-2" />
                </div>
                <div><x-input-label for="bank_account_number" value="Bank account number" /><x-text-input id="bank_account_number" name="bank_account_number" class="mbui-input mt-1" /></div>
                <div><x-input-label for="mobile_money" value="Mobile money number" /><x-text-input id="mobile_money" name="mobile_money" class="mbui-input mt-1" placeholder="e.g. 0712 345 678" /></div>
                <div class="sm:col-span-2"><x-input-label for="notes" value="Notes" /><textarea id="notes" name="notes" rows="2" class="mbui-input mt-1"></textarea></div>
                <div class="sm:col-span-2"><x-mbui.button type="submit">Save staff</x-mbui.button></div>
            </form>
        </x-mbui.card>

        <x-mbui.card class="p-6">
            <h2 class="text-base font-semibold text-gray-900">Quick settings</h2>
            <form method="POST" action="{{ route('admin.finance.staff.settings.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <x-input-label for="type" value="Type" />
                    <select id="type" name="type" class="mbui-input mt-1">
                        <option value="department">Department</option>
                        <option value="position">Position</option>
                        <option value="employment_type">Employment Type</option>
                    </select>
                </div>
                <div><x-input-label for="setting_name" value="Name" /><x-text-input id="setting_name" name="name" class="mbui-input mt-1" required /></div>
                <div><x-input-label for="code" value="Code" /><x-text-input id="code" name="code" class="mbui-input mt-1" /></div>
                <div><x-input-label for="salary_grade" value="Salary grade" /><x-text-input id="salary_grade" name="salary_grade" class="mbui-input mt-1" /></div>
                <div class="sm:col-span-2"><x-mbui.button type="submit" variant="secondary">Add setting</x-mbui.button></div>
            </form>
        </x-mbui.card>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <form method="GET" class="flex flex-col gap-3 sm:flex-row">
                <input name="q" value="{{ request('q') }}" class="mbui-input sm:w-80" placeholder="Search staff name, ID, phone or email">
                <select name="status" class="mbui-input sm:w-44">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'on_leave' => 'On Leave', 'suspended' => 'Suspended', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'retired' => 'Retired', 'inactive' => 'Inactive'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-mbui.button type="submit" variant="secondary">Filter</x-mbui.button>
            </form>
        </div>
        <x-mbui.table>
            <thead><tr class="border-b bg-gray-50"><th class="mbui-th">Staff</th><th class="mbui-th">Department</th><th class="mbui-th">Position</th><th class="mbui-th">Basic Salary</th><th class="mbui-th">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($staff as $person)
                    <tr>
                        <td class="mbui-td"><span class="font-medium text-gray-900">{{ $person->fullName() }}</span><br><span class="text-xs text-gray-500">{{ $person->staff_number }}</span></td>
                        <td class="mbui-td">{{ $person->department?->name ?? '—' }}</td>
                        <td class="mbui-td">{{ $person->position?->name ?? '—' }}</td>
                        <td class="mbui-td">TZS {{ number_format((float) $person->basic_salary) }}</td>
                        <td class="mbui-td"><x-mbui.badge appearance="neutral">{{ str_replace('_', ' ', ucfirst($person->status)) }}</x-mbui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400">No staff recorded yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $staff->links() }}</div>
</x-layouts.finance>
