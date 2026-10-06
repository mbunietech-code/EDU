<?php

namespace App\Services\Finance;

use App\Models\ActivityLog;
use App\Models\FinanceDepartment;
use App\Models\FinanceEmploymentType;
use App\Models\FinancePosition;
use App\Models\FinanceStaff;
use Illuminate\Validation\Rule;

/**
 * Finance staff directory: automatic Mhub-001 numbers, bank details and the
 * department / position / employment type settings. Shared by web and app.
 */
class StaffService
{
    public const STATUSES = ['active', 'on_leave', 'suspended', 'resigned', 'terminated', 'retired', 'inactive'];

    public static function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'finance_department_id' => ['nullable', 'exists:finance_departments,id'],
            'finance_position_id' => ['nullable', 'exists:finance_positions,id'],
            'finance_employment_type_id' => ['nullable', 'exists:finance_employment_types,id'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_name_other' => ['nullable', 'required_if:bank_name,other', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'mobile_money' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function settingRules(): array
    {
        return [
            'type' => ['required', Rule::in(['department', 'position', 'employment_type'])],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'finance_department_id' => ['nullable', 'exists:finance_departments,id'],
            'salary_grade' => ['nullable', 'string', 'max:80'],
            'seniority' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function create(array $validated, ?int $userId): FinanceStaff
    {
        if (($validated['bank_name'] ?? null) === 'other') {
            $validated['bank_name'] = $validated['bank_name_other'];
        }
        unset($validated['bank_name_other']);

        $validated['staff_number'] = $this->nextStaffNumber();
        $validated['basic_salary'] = $validated['basic_salary'] ?? 0;
        $validated['created_by'] = $userId;

        $staff = FinanceStaff::create($validated);

        ActivityLog::log('finance_staff_created', 'FinanceStaff', $staff->id, [
            'staff_number' => $staff->staff_number,
            'name' => $staff->fullName(),
        ]);

        return $staff;
    }

    public function createSetting(array $type): void
    {
        if ($type['type'] === 'department') {
            FinanceDepartment::firstOrCreate(
                ['name' => $type['name']],
                ['code' => ($type['code'] ?? null) ?: null, 'status' => 'active']
            );
        } elseif ($type['type'] === 'position') {
            $position = FinancePosition::firstOrCreate(
                ['name' => $type['name']],
                [
                    'code' => ($type['code'] ?? null) ?: null,
                    'finance_department_id' => $type['finance_department_id'] ?? null,
                    'salary_grade' => $type['salary_grade'] ?? null,
                    'seniority' => $type['seniority'] ?? FinancePosition::guessSeniority($type['name']),
                    'status' => 'active',
                ]
            );

            // Re-adding an existing position with a seniority updates its rank.
            if (! $position->wasRecentlyCreated && isset($type['seniority'])) {
                $position->update(['seniority' => $type['seniority']]);
            }
        } else {
            FinanceEmploymentType::firstOrCreate(
                ['name' => $type['name']],
                ['code' => ($type['code'] ?? null) ?: null, 'status' => 'active']
            );
        }

        ActivityLog::log('finance_hr_setting_created', 'Finance', null, ['type' => $type['type'], 'name' => $type['name']]);
    }

    public function nextStaffNumber(): string
    {
        $last = FinanceStaff::withTrashed()
            ->where('staff_number', 'like', 'Mhub-%')
            ->pluck('staff_number')
            ->map(fn ($number) => (int) substr($number, 5))
            ->max() ?? 0;

        do {
            $number = 'Mhub-'.str_pad((string) ++$last, 3, '0', STR_PAD_LEFT);
        } while (FinanceStaff::withTrashed()->where('staff_number', $number)->exists());

        return $number;
    }
}
