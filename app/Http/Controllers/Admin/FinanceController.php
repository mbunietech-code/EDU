<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FinanceCapitalEntry;
use App\Models\FinanceExpense;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tool;
use App\Services\DeletionService;
use App\Services\FinanceOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    /**
     * Validation rules for an uploaded expense receipt (image or PDF, max 5 MB).
     */
    private const RECEIPT_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public function pin()
    {
        if (session('finance_unlocked')) {
            return redirect()->route('admin.finance.dashboard');
        }

        return view('admin.finance.pin');
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        $key = 'finance-pin:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['pin' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        if ($validated['pin'] !== Setting::get('finance_pin')) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        RateLimiter::clear($key);

        $request->session()->put('finance_unlocked', true);

        ActivityLog::log('finance_unlocked', 'Finance', null, []);

        return redirect()->route('admin.finance.dashboard');
    }

    public function lock(Request $request)
    {
        $request->session()->forget('finance_unlocked');

        return redirect()->route('admin.finance.pin');
    }

    public function dashboard(FinanceOverviewService $overview)
    {
        $rows = $overview->rows();
        $totals = $overview->totals($rows);

        return view('admin.finance.dashboard', compact('rows', 'totals'));
    }

    public function capitalIndex()
    {
        $entries = FinanceCapitalEntry::with(['product', 'tool', 'creator'])->latest()->paginate(20);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        return view('admin.finance.capital', compact('entries', 'products', 'tools'));
    }

    public function capitalStore(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', 'string', 'max:255'],
            'is_loan' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['created_by'] = auth()->id();
        $validated['label'] = $this->resolveLabel($validated);

        FinanceCapitalEntry::create($validated);

        ActivityLog::log('finance_capital_added', 'FinanceCapitalEntry', null, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return back()->with('success', 'Capital entry recorded.');
    }

    public function capitalEdit(FinanceCapitalEntry $capitalEntry)
    {
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        return view('admin.finance.capital-edit', compact('capitalEntry', 'products', 'tools'));
    }

    public function capitalUpdate(Request $request, FinanceCapitalEntry $capitalEntry)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', 'string', 'max:255'],
            'is_loan' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['label'] = $this->resolveLabel($validated);

        $capitalEntry->update($validated);

        ActivityLog::log('finance_capital_updated', 'FinanceCapitalEntry', $capitalEntry->id, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return redirect()->route('admin.finance.capital.index')->with('success', 'Capital entry updated.');
    }

    public function capitalDestroy(Request $request, FinanceCapitalEntry $capitalEntry, DeletionService $deletionService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($capitalEntry, $validated['reason']);

        return back()->with('success', 'Capital entry removed.');
    }

    public function expenseIndex()
    {
        $expenses = FinanceExpense::with(['product', 'tool', 'creator'])->latest('spent_at')->paginate(20);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        $receiptsSupported = $this->receiptsSupported();

        return view('admin.finance.expenses', compact('expenses', 'products', 'tools', 'receiptsSupported'));
    }

    public function expenseStore(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', Rule::in([
                'Operating Expenses (OPEX)',
                'Cost of Goods Sold (COGS)',
                'Financial Expenses',
                'Depreciation & Amortization',
                'Miscellaneous',
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => self::RECEIPT_RULES,
            'spent_at' => ['required', 'date'],
        ]);

        unset($validated['receipt']);
        $validated['created_by'] = auth()->id();
        $validated['label'] = $this->resolveLabel($validated);

        if ($this->receiptsSupported() && $request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('finance-receipts', 'private');
        }

        FinanceExpense::create($validated);

        ActivityLog::log('finance_expense_added', 'FinanceExpense', null, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return back()->with('success', 'Expense recorded.');
    }

    public function expenseEdit(FinanceExpense $expense)
    {
        $products = Product::orderBy('name')->get(['id', 'name']);
        $tools = Tool::orderBy('name')->get(['id', 'name']);

        $receiptsSupported = $this->receiptsSupported();

        return view('admin.finance.expense-edit', compact('expense', 'products', 'tools', 'receiptsSupported'));
    }

    public function expenseUpdate(Request $request, FinanceExpense $expense)
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'tool_id' => ['nullable', 'exists:tools,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', Rule::in([
                'Operating Expenses (OPEX)',
                'Cost of Goods Sold (COGS)',
                'Financial Expenses',
                'Depreciation & Amortization',
                'Miscellaneous',
            ])],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt' => self::RECEIPT_RULES,
            'remove_receipt' => ['nullable', 'boolean'],
            'spent_at' => ['required', 'date'],
        ]);

        $removeReceipt = (bool) ($validated['remove_receipt'] ?? false);
        unset($validated['receipt'], $validated['remove_receipt']);

        $validated['label'] = $this->resolveLabel($validated);

        if ($this->receiptsSupported()) {
            if ($request->hasFile('receipt')) {
                $this->deleteReceiptFile($expense->receipt_path);
                $validated['receipt_path'] = $request->file('receipt')->store('finance-receipts', 'private');
            } elseif ($removeReceipt) {
                $this->deleteReceiptFile($expense->receipt_path);
                $validated['receipt_path'] = null;
            }
        }

        $expense->update($validated);

        ActivityLog::log('finance_expense_updated', 'FinanceExpense', $expense->id, ['label' => $validated['label'], 'amount' => $validated['amount']]);

        return redirect()->route('admin.finance.expenses.index')->with('success', 'Expense updated.');
    }

    public function expenseReceipt(Request $request, FinanceExpense $expense): StreamedResponse
    {
        abort_unless($expense->hasReceipt(), 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($expense->receipt_path), 404);

        $extension = pathinfo($expense->receipt_path, PATHINFO_EXTENSION) ?: 'file';
        $name = 'receipt-'.\Illuminate\Support\Str::slug($expense->label ?: 'expense').'-'.$expense->id.'.'.$extension;

        // ?download=1 forces a file download; otherwise it opens inline.
        return $request->boolean('download')
            ? $disk->download($expense->receipt_path, $name)
            : $disk->response($expense->receipt_path, $name);
    }

    public function expenseDestroy(Request $request, FinanceExpense $expense, DeletionService $deletionService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $receiptPath = $expense->receipt_path;

        $deletionService->delete($expense, $validated['reason']);

        $this->deleteReceiptFile($receiptPath);

        return back()->with('success', 'Expense removed.');
    }

    protected function deleteReceiptFile(?string $path): void
    {
        if (filled($path) && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    /**
     * Whether the receipt_path column exists yet (the schema change may not
     * have been applied on this database). Cached per request.
     */
    protected function receiptsSupported(): bool
    {
        static $supported = null;

        if ($supported === null) {
            $supported = \Illuminate\Support\Facades\Schema::hasColumn('finance_expenses', 'receipt_path');
        }

        return $supported;
    }

    protected function resolveLabel(array $validated): string
    {
        if (! empty($validated['product_id'])) {
            return Product::find($validated['product_id'])->name;
        }

        if (! empty($validated['tool_id'])) {
            return Tool::find($validated['tool_id'])->name;
        }

        return $validated['label'];
    }
}
