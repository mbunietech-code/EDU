<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\FinanceAccess;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceStaff;
use App\Models\FinanceStaffContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff contracts in the app (Finance → Contracts on the web): the list with
 * contracts ending within 30 days and expired ones, and a new contract.
 */
class FinanceContractController extends Controller
{
    use FinanceAccess;

    public const STATUSES = ['draft', 'active', 'expiring', 'expired', 'renewed', 'terminated', 'cancelled'];

    public function index(Request $request): JsonResponse
    {
        $this->finance($request);
        $filter = in_array($request->query('filter'), ['all', 'active', 'expiring', 'expired'], true) ? $request->query('filter') : 'all';
        $today = now()->toDateString();
        $soon = now()->addDays(30)->toDateString();

        $expiring = fn ($q) => $q->where('status', 'active')->whereBetween('end_date', [$today, $soon]);
        $expired = fn ($q) => $q->whereNotNull('end_date')->where('end_date', '<', $today)
            ->whereNotIn('status', ['renewed', 'terminated', 'cancelled']);

        $query = FinanceStaffContract::with(['staff', 'department', 'position'])
            ->when($filter === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($filter === 'expiring', $expiring)
            ->when($filter === 'expired', $expired)
            ->when($filter === 'expiring', fn ($q) => $q->orderBy('end_date'), fn ($q) => $q->latest('start_date'));

        $page = $query->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (FinanceStaffContract $c) => $this->row($c))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'counts' => [
                    'active' => FinanceStaffContract::where('status', 'active')->count(),
                    'expiring' => $expiring(FinanceStaffContract::query())->count(),
                    'expired' => $expired(FinanceStaffContract::query())->count(),
                ],
                'statuses' => self::STATUSES,
                'staff' => FinanceStaff::orderBy('first_name')->orderBy('last_name')->get()
                    ->map(fn (FinanceStaff $s) => ['id' => $s->id, 'name' => $s->fullName(), 'staff_number' => $s->staff_number])->values(),
            ],
        ]);
    }

    /** Same rules as the web form; an active contract replaces the staff member's current one. */
    public function store(Request $request): JsonResponse
    {
        $this->finance($request);
        $validated = $request->validate([
            'finance_staff_id' => ['required', 'exists:finance_staff,id'],
            'contract_number' => ['nullable', 'string', 'max:80', 'unique:finance_staff_contracts,contract_number'],
            'contract_type' => ['nullable', 'string', 'max:120'],
            'finance_department_id' => ['nullable', 'exists:finance_departments,id'],
            'finance_position_id' => ['nullable', 'exists:finance_positions,id'],
            'finance_employment_type_id' => ['nullable', 'exists:finance_employment_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'salary_grade' => ['nullable', 'string', 'max:80'],
            'leave_entitlement_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff = FinanceStaff::findOrFail($validated['finance_staff_id']);
        $validated['contract_number'] = ($validated['contract_number'] ?? null) ?: 'CNT-'.now()->format('Ymd').'-'.str_pad((string) (FinanceStaffContract::count() + 1), 4, '0', STR_PAD_LEFT);
        $validated['finance_department_id'] ??= $staff->finance_department_id;
        $validated['finance_position_id'] ??= $staff->finance_position_id;
        $validated['finance_employment_type_id'] ??= $staff->finance_employment_type_id;
        $validated['created_by'] = $request->user()->id;

        $contract = FinanceStaffContract::create($validated);

        if ($contract->status === 'active') {
            FinanceStaffContract::where('finance_staff_id', $staff->id)
                ->where('id', '!=', $contract->id)
                ->where('status', 'active')
                ->update(['status' => 'renewed']);

            $staff->update([
                'basic_salary' => $contract->basic_salary,
                'finance_department_id' => $contract->finance_department_id,
                'finance_position_id' => $contract->finance_position_id,
                'finance_employment_type_id' => $contract->finance_employment_type_id,
                'status' => 'active',
            ]);
        }

        ActivityLog::log('finance_contract_created', 'FinanceStaffContract', $contract->id, [
            'contract_number' => $contract->contract_number,
            'staff_id' => $staff->staff_number,
        ]);

        return response()->json(['data' => $this->row($contract->load(['staff', 'department', 'position'])), 'message' => 'Contract '.$contract->contract_number.' saved.'], 201);
    }

    private function row(FinanceStaffContract $c): array
    {
        $days = $c->end_date ? (int) now()->startOfDay()->diffInDays($c->end_date, false) : null;

        return [
            'id' => $c->id,
            'contract_number' => $c->contract_number,
            'contract_type' => $c->contract_type,
            'status' => $c->status,
            'staff' => $c->staff?->fullName(),
            'staff_number' => $c->staff?->staff_number,
            'department' => $c->department?->name,
            'position' => $c->position?->name,
            'start_date' => $c->start_date?->toDateString(),
            'end_date' => $c->end_date?->toDateString(),
            'days_left' => $days,
            'basic_salary' => (float) $c->basic_salary,
            'notes' => $c->notes,
        ];
    }
}
