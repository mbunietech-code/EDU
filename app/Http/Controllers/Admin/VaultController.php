<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVaultUnlocked;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\VaultEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Password vault for company accounts (hosting, domains, email, social media…).
 *
 * - Only people given "vault.view" (super admins always) can open it, and only
 *   after the vault PIN — like Finance. "vault.manage" adds / edits / deletes.
 * - Secrets are encrypted in the database and never sent to the page until
 *   someone presses "Show" or "Copy" on one entry; every such view is logged.
 * - It locks itself after a few idle minutes.
 */
class VaultController extends Controller
{
    private const PIN_SETTING = 'vault_pin_hash';

    // ------------------------------------------------------------------ PIN

    public function pin(Request $request): View|RedirectResponse
    {
        if ($this->isUnlocked($request)) {
            return redirect()->route('admin.vault.index');
        }

        return view('admin.vault.pin', [
            'hasPin' => $this->pinHash() !== '',
            'canSetPin' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'digits:6']]);
        $key = 'vault-pin:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pin' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $hash = $this->pinHash();
        if ($hash === '' || ! Hash::check($data['pin'], $hash)) {
            RateLimiter::hit($key, 300);
            ActivityLog::log('vault_pin_failed', 'Vault');

            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('vault_unlocked_at', now()->timestamp);
        ActivityLog::log('vault_unlocked', 'Vault');

        return redirect()->route('admin.vault.index');
    }

    /** Super admin: set the first PIN, or change it (current PIN needed once one exists). */
    public function setPin(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $hasPin = $this->pinHash() !== '';
        $data = $request->validate([
            'current_pin' => [$hasPin ? 'required' : 'nullable', 'digits:6'],
            'new_pin' => ['required', 'digits:6', 'confirmed'],
        ]);

        if ($hasPin && ! Hash::check((string) $data['current_pin'], $this->pinHash())) {
            ActivityLog::log('vault_pin_change_failed', 'Vault');

            return back()->withErrors(['current_pin' => 'The current PIN is wrong.']);
        }

        Setting::set(self::PIN_SETTING, Hash::make($data['new_pin']), 'string', 'vault');
        ActivityLog::log($hasPin ? 'vault_pin_changed' : 'vault_pin_set', 'Vault');
        $request->session()->forget('vault_unlocked_at');

        return redirect()->route('admin.vault.pin')->with('success', 'Vault PIN saved. Unlock the vault with the new PIN.');
    }

    public function lock(Request $request): RedirectResponse
    {
        $request->session()->forget('vault_unlocked_at');

        return redirect()->route('admin.dashboard');
    }

    // -------------------------------------------------------------- entries

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');

        $entries = VaultEntry::with('editor')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('url', 'like', "%{$q}%")))
            ->when(isset(VaultEntry::CATEGORIES[$category]), fn ($w) => $w->where('category', $category))
            ->orderBy('category')->orderBy('name')
            ->get();

        return view('admin.vault.index', [
            'entries' => $entries,
            'q' => $q,
            'category' => $category,
            'canManage' => $request->user()->can('vault.manage'),
            'idleMinutes' => EnsureVaultUnlocked::IDLE_MINUTES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $entry = VaultEntry::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        ActivityLog::log('vault_entry_added', 'VaultEntry', $entry->id, ['name' => $entry->name]);

        return redirect()->route('admin.vault.index')->with('success', "Saved \"{$entry->name}\".");
    }

    public function update(Request $request, VaultEntry $entry): RedirectResponse
    {
        $data = $this->validated($request, false);
        // Blank secret fields keep what is saved (the form never shows them).
        foreach (['username', 'password', 'notes'] as $field) {
            if (($data[$field] ?? '') === '' && ! $request->boolean("clear_{$field}")) {
                unset($data[$field]);
            }
        }
        $changed = array_keys(array_diff_key($data, ['name' => 1, 'category' => 1, 'url' => 1]));

        $entry->update($data + ['updated_by' => $request->user()->id]);
        ActivityLog::log('vault_entry_updated', 'VaultEntry', $entry->id, ['name' => $entry->name, 'secrets_changed' => $changed]);

        return redirect()->route('admin.vault.index')->with('success', "Updated \"{$entry->name}\".");
    }

    public function destroy(Request $request, VaultEntry $entry): RedirectResponse
    {
        ActivityLog::log('vault_entry_deleted', 'VaultEntry', $entry->id, ['name' => $entry->name]);
        $entry->delete(); // soft delete: a super admin can still recover it from the database

        return redirect()->route('admin.vault.index')->with('success', "Removed \"{$entry->name}\".");
    }

    /** One entry's secrets, on request only. Every call is logged. */
    public function reveal(Request $request, VaultEntry $entry): JsonResponse
    {
        ActivityLog::log('vault_entry_viewed', 'VaultEntry', $entry->id, ['name' => $entry->name, 'what' => $request->input('what', 'all')]);

        return response()->json([
            'username' => $entry->username,
            'password' => $entry->password,
            'notes' => $entry->notes,
        ])->header('Cache-Control', 'no-store');
    }

    // -------------------------------------------------------------- helpers

    private function isUnlocked(Request $request): bool
    {
        $at = (int) $request->session()->get('vault_unlocked_at', 0);

        return $at > 0 && now()->timestamp - $at <= EnsureVaultUnlocked::IDLE_MINUTES * 60;
    }

    private function pinHash(): string
    {
        return (string) Setting::get(self::PIN_SETTING, '');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(VaultEntry::CATEGORIES))],
            'url' => ['nullable', 'string', 'max:500'],
            'username' => ['nullable', 'string', 'max:500'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
