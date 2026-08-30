<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct(protected CredentialService $credentials)
    {
    }

    private function gate(Request $request, string $ability = 'accounts.view'): void
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin() || $user->hasPermission($ability),
            403,
            'You do not have access to shared accounts.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->gate($request);

        $accounts = Account::with('product:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => collect($accounts->items())->map(fn (Account $a) => $this->row($a))->all(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'available' => Account::where('status', 'available')->count(),
                'assigned' => Account::where('status', 'assigned')->count(),
            ],
        ]);
    }

    public function show(Request $request, Account $account): JsonResponse
    {
        $this->gate($request);
        $account->load(['product:id,name', 'subscriptions.user:id,name,email']);

        return response()->json(['data' => array_merge($this->row($account), [
            'description' => $account->description,
            'has_credentials' => $account->credentials !== null,
            'subscriptions' => $account->subscriptions->map(fn ($s) => [
                'id' => $s->id,
                'user' => $s->user->name ?? '—',
                'status' => $s->status,
            ])->all(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->gate($request, 'accounts.manage');

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'credentials' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['available', 'suspended', 'maintenance', 'archived'])],
        ]);

        $data['credentials'] = ! empty($data['credentials'])
            ? $this->credentials->encrypt($data['credentials'])
            : null;

        $account = Account::create($data);
        ActivityLog::log('account_created', 'Account', $account->id, ['name' => $account->name]);

        return response()->json(['data' => ['id' => $account->id], 'message' => 'Account created.'], 201);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $this->gate($request, 'accounts.manage');

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'credentials' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['available', 'assigned', 'suspended', 'maintenance', 'expired', 'archived'])],
        ]);

        if (! empty($data['credentials'])) {
            $data['credentials'] = $this->credentials->encrypt($data['credentials']);
        } else {
            unset($data['credentials']);
        }

        $account->update($data);
        ActivityLog::log('account_updated', 'Account', $account->id, ['name' => $account->name]);

        return response()->json(['message' => 'Account updated.']);
    }

    public function archive(Request $request, Account $account): JsonResponse
    {
        $this->gate($request, 'accounts.manage');

        if ($account->status === 'assigned') {
            return response()->json([
                'message' => 'Cannot archive an assigned account. Release the assignment first.',
            ], 422);
        }

        $account->update(['status' => 'archived']);
        ActivityLog::log('account_archived', 'Account', $account->id, ['name' => $account->name]);

        return response()->json(['message' => 'Account archived.']);
    }

    public function reveal(Request $request, Account $account): JsonResponse
    {
        $this->gate($request, 'accounts.manage');

        if ($account->credentials === null) {
            return response()->json(['message' => 'This account has no stored credentials.'], 422);
        }

        $value = $this->credentials->decryptValue($account->credentials);

        ActivityLog::log('account_credentials_viewed', 'Account', $account->id, [
            'name' => $account->name, 'via' => 'app',
        ]);

        return response()->json(['data' => ['credentials' => $value]]);
    }

    public function targets(Request $request): JsonResponse
    {
        $this->gate($request);

        return response()->json(['data' => [
            'products' => \App\Models\Product::orderBy('name')->get(['id', 'name']),
        ]]);
    }

    private function row(Account $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'product' => $a->product->name ?? '—',
            'product_id' => $a->product_id,
            'status' => $a->status,
            'has_credentials' => $a->credentials !== null,
        ];
    }
}
