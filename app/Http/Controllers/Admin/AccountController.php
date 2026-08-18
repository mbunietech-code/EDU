<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Http\Requests\Admin\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Product;
use App\Services\CredentialService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(protected CredentialService $credentialService)
    {
    }

    public function index(Request $request)
    {
        $accounts = Account::with(['product'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->where('product_id', $request->input('product_id'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $products = Product::all();

        return view('admin.accounts.create', compact('products'));
    }

    public function store(StoreAccountRequest $request)
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();

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

    public function edit(Account $account)
    {
        $products = Product::all();

        return view('admin.accounts.edit', compact('account', 'products'));
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $this->authorize('update', $account);

        $validated = $request->validated();

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