<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Http\Requests\Admin\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Product;
use App\Services\CredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(protected CredentialService $credentialService)
    {
    }

    public function index(Request $request)
    {
        $accounts = Account::with([
                'product',
                // Only the holder now, for the "Current user" column.
                'subscriptions' => fn ($q) => $q->whereIn('status', Account::HOLDING_STATUSES)->with('user:id,name,email'),
            ])
            // Account name, or the name / email of anyone who used it (now or before).
            ->when(trim((string) $request->input('search')) !== '', function ($query) use ($request) {
                $term = trim((string) $request->input('search'));
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhereHas('subscriptions.user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->where('product_id', $request->input('product_id'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Paid customers whose account still needs its login details.
        $awaiting = Account::awaitingSetup()
            ->with(['product:id,name', 'subscriptions' => fn ($q) => $q->whereIn('status', Account::HOLDING_STATUSES)->with('user:id,name,email')])
            ->oldest()
            ->get();

        return view('admin.accounts.index', compact('accounts', 'awaiting'));
    }

    /** AI plans we bought: when each one was bought and when it ends. */
    public function plans(Request $request)
    {
        $all = Account::forPlansPage()->get();
        $counts = $all->countBy(fn (Account $a) => $a->planState());
        $filter = in_array($request->query('filter'), ['active', 'expiring', 'expired', 'unknown'], true) ? $request->query('filter') : 'all';
        $accounts = $filter === 'all' ? $all : $all->filter(fn (Account $a) => $a->planState() === $filter)->values();
        $spent = $all->whereNotNull('cost')->groupBy(fn (Account $a) => $a->cost_currency ?: 'TZS')
            ->map(fn ($group) => (float) $group->sum('cost'));

        return view('admin.accounts.plans', compact('accounts', 'counts', 'filter', 'spent') + ['total' => $all->count()]);
    }

    public function create()
    {
        $products = Product::all();

        return view('admin.accounts.create', compact('products'));
    }

    public function store(StoreAccountRequest $request)
    {
        $this->authorize('create', Account::class);

        $validated = collect($request->validated())->except(['login_email', 'login_password'])->all();

        if (! empty($validated['credentials'])) {
            $validated['credentials'] = $this->credentialService->encrypt($validated['credentials']);
        } else {
            $validated['credentials'] = null;
        }

        $account = Account::create($validated);

        \App\Models\ActivityLog::log(
            'account_created',
            'Account',
            $account->id,
            ['name' => $account->name, 'product_id' => $account->product_id]
        );

        return redirect()->route('admin.accounts.index')
            ->with('success', 'Account created.');
    }

    /**
     * Several logins at once, one per line ("email:password"; a comma, tab or
     * space also works). Logins already listed for the product are skipped.
     */
    public function bulkStore(Request $request)
    {
        $this->authorize('create', Account::class);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'lines' => ['required', 'string', 'max:40000'],
            'plan_name' => ['nullable', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date'],
        ], [], ['lines' => 'logins']);

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['lines']) ?: [])));

        if (count($lines) > 200) {
            return back()->withInput($request->except('lines'))->withErrors(['lines' => 'Add at most 200 logins at a time.']);
        }

        $pairs = [];
        $bad = [];
        foreach ($lines as $number => $line) {
            $parts = preg_split('/\s*[:,\t]\s*|\s+/', $line, 2);
            $login = trim($parts[0] ?? '');
            $password = trim($parts[1] ?? '');

            if ($login === '' || $password === '' || mb_strlen($login) > 255 || mb_strlen($password) > 500) {
                $bad[] = $number + 1;

                continue;
            }

            $pairs[mb_strtolower($login)] = [$login, $password];
        }

        if ($bad !== []) {
            return back()->withInput($request->except('lines'))
                ->withErrors(['lines' => 'Line '.implode(', ', array_slice($bad, 0, 10)).' needs both a login and a password.']);
        }

        $known = Account::where('product_id', $data['product_id'])
            ->whereIn('name', array_column($pairs, 0))
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower($name))
            ->all();

        $created = 0;
        DB::transaction(function () use ($pairs, $known, $data, &$created) {
            foreach ($pairs as $key => [$login, $password]) {
                if (in_array($key, $known, true)) {
                    continue;
                }

                $account = Account::create([
                    'product_id' => $data['product_id'],
                    'name' => $login,
                    'credentials' => $this->credentialService->encrypt("email: {$login}\npassword: {$password}"),
                    'status' => 'available',
                    'plan_name' => $data['plan_name'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                ]);
                $created++;

                \App\Models\ActivityLog::log('account_created', 'Account', $account->id, [
                    'name' => $account->name, 'product_id' => $account->product_id, 'via' => 'bulk',
                ]);
            }
        });

        $skipped = count($pairs) - $created;

        return redirect()->route('admin.accounts.index', ['product_id' => $data['product_id']])
            ->with('success', "{$created} account(s) added.".($skipped ? " {$skipped} already in the list were skipped." : ''));
    }

    public function edit(Account $account)
    {
        $products = Product::all();

        return view('admin.accounts.edit', compact('account', 'products'));
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $this->authorize('update', $account);

        $validated = collect($request->validated())->except(['login_email', 'login_password'])->all();

        if (! empty($validated['credentials'])) {
            $validated['credentials'] = $this->credentialService->encrypt($validated['credentials']);
        } else {
            unset($validated['credentials']);
        }

        $account->update($validated);

        \App\Models\ActivityLog::log(
            'account_updated',
            'Account',
            $account->id,
            ['name' => $account->name]
        );

        return redirect()->route('admin.accounts.index')
            ->with('success', 'Account updated.');
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        if ($account->status === 'assigned') {
            return back()->with('error', 'Cannot archive an assigned account. Release the assignment first.');
        }

        $account->update(['status' => 'archived']);

        \App\Models\ActivityLog::log(
            'account_archived',
            'Account',
            $account->id,
            ['name' => $account->name]
        );

        return back()->with('success', 'Account archived.');
    }

    public function show(Account $account)
    {
        $account->load(['product', 'subscriptions.user']);

        return view('admin.accounts.show', compact('account'));
    }

    public function decryptCredentials(Account $account)
    {
        if ($account->credentials === null) {
            return back()->with('error', 'This account has no stored credentials.');
        }

        $credentials = $this->credentialService->decryptValue($account->credentials);

        \App\Models\ActivityLog::log(
            'account_credentials_viewed',
            'Account',
            $account->id,
            ['name' => $account->name]
        );

        return back()->with('decrypted_credentials', $credentials)
            ->with('success', 'Credentials decrypted. This action has been logged.');
    }
}