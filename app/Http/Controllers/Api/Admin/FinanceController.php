<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\Setting;
use App\Services\FinanceOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    private const UNLOCK_MINUTES = 30;

    private const CATEGORIES = [
        'Operating Expenses (OPEX)',
        'Cost of Goods Sold (COGS)',
        'Financial Expenses',
        'Depreciation & Amortization',
        'Miscellaneous',
    ];

    private function gate(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin() || $user->hasPermission('finance.access'),
            403,
            'You do not have access to Finance.',
        );
    }

    private function unlockKey(Request $request): string
    {
        return 'finance-unlock:token:'.optional($request->user()->currentAccessToken())->id;
    }

    private function assertUnlocked(Request $request): void
    {
        abort_unless(
            Cache::get($this->unlockKey($request)) === true,
            423,
            'Finance is locked. Enter the PIN to unlock.',
        );
    }

    public function unlock(Request $request): JsonResponse
    {
        $this->gate($request);

        $data = $request->validate(['pin' => ['required', 'digits:6']]);

        $key = 'finance-pin:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ], 429);
        }

        if (! hash_equals((string) Setting::get('finance_pin'), $data['pin'])) {
            RateLimiter::hit($key, 300);

            return response()->json(['message' => 'Incorrect PIN.'], 422);
        }

        RateLimiter::clear($key);
        Cache::put($this->unlockKey($request), true, now()->addMinutes(self::UNLOCK_MINUTES));
        ActivityLog::log('finance_unlocked', 'Finance', null, ['via' => 'app']);

        return response()->json(['data' => [
            'unlocked' => true,
            'expires_in' => self::UNLOCK_MINUTES * 60,
        ]]);
    }

    public function status(Request $request): JsonResponse
    {
        $this->gate($request);

        return response()->json(['data' => [
            'unlocked' => Cache::get($this->unlockKey($request)) === true,
            'pin_set' => (string) Setting::get('finance_pin') !== '',
        ]]);
    }

    public function overview(Request $request, FinanceOverviewService $overview): JsonResponse
    {
        $this->gate($request);
        $this->assertUnlocked($request);

        $rows = $overview->rows();
        $totals = $overview->totals($rows);

        return response()->json(['data' => [
            'rows' => collect($rows)->map(fn ($r) => [
                'label' => $r['label'],
                'type' => $r['type'] ?? '',
                'capital' => (float) $r['capital'],
                'income' => (float) $r['income'],
                'expenses' => (float) $r['expenses'],
                'balance' => (float) $r['balance'],
            ])->values(),
            'totals' => [
                'capital' => (float) $totals['capital'],
                'income' => (float) $totals['income'],
                'expenses' => (float) $totals['expenses'],
                'balance' => (float) $totals['balance'],
            ],
        ]]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $this->gate($request);
        $this->assertUnlocked($request);

        $expenses = FinanceExpense::with(['product:id,name', 'tool:id,name', 'creator:id,name'])
            ->latest('spent_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($expenses->items())->map(fn (FinanceExpense $e) => [
                'id' => $e->id,
                'label' => $e->label,
                'amount' => (float) $e->amount,
                'amount_label' => 'TZS '.number_format((float) $e->amount),
                'category' => $e->category,
                'description' => $e->description,
                'target' => $e->product->name ?? $e->tool->name ?? 'General',
                'spent_at' => optional($e->spent_at)->toDateString(),
                'created_by' => $e->creator->name ?? null,
                'has_receipt' => ! empty($e->receipt_path),
            ])->all(),
            'meta' => ['current_page' => $expenses->currentPage(), 'last_page' => $expenses->lastPage()],
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $this->gate($request);
        $this->assertUnlocked($request);

        $data = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'spent_at' => ['required', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $data['created_by'] = $request->user()->id;

        $receipt = $data['receipt'] ?? null;
        unset($data['receipt']);

        if ($receipt && Schema::hasColumn('finance_expenses', 'receipt_path')) {
            $data['receipt_path'] = $request->file('receipt')->store('finance-receipts', 'private');
        }

        $expense = FinanceExpense::create($data);
        ActivityLog::log('finance_expense_added', 'FinanceExpense', $expense->id, [
            'label' => $expense->label, 'amount' => $expense->amount,
        ]);

        return response()->json(['data' => ['id' => $expense->id], 'message' => 'Expense recorded.'], 201);
    }

    public function receipt(Request $request, FinanceExpense $expense): StreamedResponse
    {
        $this->gate($request);
        $this->assertUnlocked($request);

        abort_unless($expense->receipt_path && Storage::disk('private')->exists($expense->receipt_path), 404);

        return Storage::disk('private')->download($expense->receipt_path);
    }

    public function capital(Request $request): JsonResponse
    {
        $this->gate($request);
        $this->assertUnlocked($request);

        $entries = FinanceCapitalEntry::with(['product:id,name', 'tool:id,name', 'creator:id,name'])
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => collect($entries->items())->map(fn (FinanceCapitalEntry $c) => [
                'id' => $c->id,
                'label' => $c->label,
                'amount' => (float) $c->amount,
                'amount_label' => 'TZS '.number_format((float) $c->amount),
                'source' => $c->source,
                'is_loan' => (bool) $c->is_loan,
                'notes' => $c->notes,
                'target' => $c->product->name ?? $c->tool->name ?? 'General',
                'created_by' => $c->creator->name ?? null,
            ])->all(),
            'meta' => ['current_page' => $entries->currentPage(), 'last_page' => $entries->lastPage()],
        ]);
    }

    public function storeCapital(Request $request): JsonResponse
    {
        $this->gate($request);
        $this->assertUnlocked($request);

        $data = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', 'string', 'max:255'],
            'is_loan' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['created_by'] = $request->user()->id;

        $entry = FinanceCapitalEntry::create($data);
        ActivityLog::log('finance_capital_added', 'FinanceCapitalEntry', $entry->id, [
            'label' => $entry->label, 'amount' => $entry->amount,
        ]);

        return response()->json(['data' => ['id' => $entry->id], 'message' => 'Capital entry recorded.'], 201);
    }

    public function targets(Request $request): JsonResponse
    {
        $this->gate($request);

        return response()->json(['data' => [
            'products' => \App\Models\Product::orderBy('name')->get(['id', 'name']),
            'tools' => \App\Models\Tool::orderBy('name')->get(['id', 'name']),
            'categories' => self::CATEGORIES,
        ]]);
    }
}
