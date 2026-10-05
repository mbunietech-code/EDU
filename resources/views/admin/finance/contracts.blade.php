<x-layouts.finance title="Contracts" header="Finance">
    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Staff Contracts</h1>
            <p class="mt-1 text-sm text-gray-500">Track active, expiring and historical contracts without overwriting history.</p>
        </div>
    </div>

    <x-mbui.card class="mt-6 p-6">
        <h2 class="text-base font-semibold text-gray-900">Create contract</h2>
        <form method="POST" action="{{ route('admin.finance.contracts.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
            @csrf
            <div>
                <x-input-label for="finance_staff_id" value="Staff" />
                <select id="finance_staff_id" name="finance_staff_id" class="mbui-input mt-1" required>
                    <option value="">Choose staff</option>
                    @foreach ($staff as $person)<option value="{{ $person->id }}">{{ $person->staff_number }} · {{ $person->fullName() }}</option>@endforeach
                </select>
            </div>
            <div><x-input-label for="contract_number" value="Contract number" /><x-text-input id="contract_number" name="contract_number" class="mbui-input mt-1" placeholder="Auto if empty" /></div>
            <div><x-input-label for="contract_type" value="Contract type" /><x-text-input id="contract_type" name="contract_type" class="mbui-input mt-1" placeholder="Fixed term, permanent..." /></div>
            <div>
                <x-input-label for="finance_department_id" value="Department" />
                <select id="finance_department_id" name="finance_department_id" class="mbui-input mt-1">
                    <option value="">Use staff department</option>
                    @foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="finance_position_id" value="Position" />
                <select id="finance_position_id" name="finance_position_id" class="mbui-input mt-1">
                    <option value="">Use staff position</option>
                    @foreach ($positions as $position)<option value="{{ $position->id }}">{{ $position->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="finance_employment_type_id" value="Employment type" />
                <select id="finance_employment_type_id" name="finance_employment_type_id" class="mbui-input mt-1">
                    <option value="">Use staff type</option>
                    @foreach ($employmentTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                </select>
            </div>
            <div><x-input-label for="start_date" value="Start date" /><x-text-input id="start_date" type="date" name="start_date" class="mbui-input mt-1" required /></div>
            <div><x-input-label for="end_date" value="End date" /><x-text-input id="end_date" type="date" name="end_date" class="mbui-input mt-1" /></div>
            <div><x-input-label for="basic_salary" value="Basic salary" /><x-text-input id="basic_salary" type="number" min="0" step="0.01" name="basic_salary" class="mbui-input mt-1" required /></div>
            <div><x-input-label for="salary_grade" value="Salary grade" /><x-text-input id="salary_grade" name="salary_grade" class="mbui-input mt-1" /></div>
            <div><x-input-label for="leave_entitlement_days" value="Leave days" /><x-text-input id="leave_entitlement_days" type="number" min="0" max="365" name="leave_entitlement_days" class="mbui-input mt-1" /></div>
            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mbui-input mt-1">
                    @foreach (['draft', 'active', 'expiring', 'expired', 'renewed', 'terminated', 'cancelled'] as $status)
                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3"><x-input-label for="notes" value="Notes" /><textarea id="notes" name="notes" rows="2" class="mbui-input mt-1"></textarea></div>
            <div class="md:col-span-3"><x-mbui.button type="submit">Save contract</x-mbui.button></div>
        </form>
    </x-mbui.card>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead><tr class="border-b bg-gray-50"><th class="mbui-th">Contract</th><th class="mbui-th">Staff</th><th class="mbui-th">Department</th><th class="mbui-th">Period</th><th class="mbui-th">Salary</th><th class="mbui-th">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($contracts as $contract)
                    <tr>
                        <td class="mbui-td font-medium text-gray-900">{{ $contract->contract_number }}</td>
                        <td class="mbui-td">{{ $contract->staff?->fullName() }}</td>
                        <td class="mbui-td">{{ $contract->department?->name ?? '—' }}</td>
                        <td class="mbui-td">{{ $contract->start_date?->format('d M Y') }} — {{ $contract->end_date?->format('d M Y') ?? 'Open' }}</td>
                        <td class="mbui-td">TZS {{ number_format((float) $contract->basic_salary) }}</td>
                        <td class="mbui-td"><x-mbui.badge appearance="{{ $contract->status === 'active' ? 'success' : 'neutral' }}">{{ ucfirst($contract->status) }}</x-mbui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="mbui-td text-center text-gray-400">No contracts recorded yet</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>
    <div class="mt-6">{{ $contracts->links() }}</div>
</x-layouts.finance>
