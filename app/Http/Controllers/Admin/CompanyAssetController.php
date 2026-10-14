<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CompanyAsset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Company assets: everything Mbunie pays for (domains, hosting, servers,
 * software, licences, devices…) — when it was bought, when it ends, where it
 * is bought and who looks after it. Reminders: assets:remind (daily).
 */
class CompanyAssetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('assets.view');

        $status = (string) $request->query('status', 'all');
        $category = (string) $request->query('category', '');
        $search = trim((string) $request->query('q', ''));
        $today = Carbon::today();
        $soon = $today->copy()->addDays(CompanyAsset::SOON_DAYS);

        $assets = CompanyAsset::query()
            ->with('responsible:id,name,job_title')
            ->when($category !== '' && isset(CompanyAsset::CATEGORIES[$category]), fn ($q) => $q->where('category', $category))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('vendor', 'like', '%'.$search.'%')
                ->orWhere('account', 'like', '%'.$search.'%')))
            ->when($status === 'attention', fn ($q) => $q->whereNotNull('expires_at')->whereDate('expires_at', '<=', $soon))
            ->when($status === 'expired', fn ($q) => $q->whereDate('expires_at', '<', $today))
            ->when($status === 'soon', fn ($q) => $q->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $soon))
            ->when($status === 'active', fn ($q) => $q->whereDate('expires_at', '>', $soon))
            ->when($status === 'no_expiry', fn ($q) => $q->whereNull('expires_at'))
            // Soonest end first; things that never end last.
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'all' => CompanyAsset::query()->count(),
            'expired' => CompanyAsset::query()->whereDate('expires_at', '<', $today)->count(),
            'soon' => CompanyAsset::query()->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $soon)->count(),
        ];

        // Yearly cost per currency (one-time purchases left out).
        $perYear = ['monthly' => 12, 'quarterly' => 4, 'yearly' => 1, 'two_years' => 0.5];
        $yearly = CompanyAsset::query()->whereNotNull('cost')->where('billing_cycle', '!=', 'one_time')->get(['cost', 'currency', 'billing_cycle'])
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->sum(fn ($a) => (float) $a->cost * ($perYear[$a->billing_cycle] ?? 0)))
            ->filter(fn ($sum) => $sum > 0);

        return view('admin.assets.index', compact('assets', 'counts', 'yearly', 'status', 'category', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('assets.manage');

        return view('admin.assets.form', ['asset' => new CompanyAsset(['currency' => 'TZS', 'billing_cycle' => 'yearly']), 'admins' => $this->admins()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $asset = CompanyAsset::create($this->validated($request) + ['created_by' => $request->user()->id]);

        ActivityLog::log('company_asset_created', 'CompanyAsset', $asset->id, ['name' => $asset->name]);

        return redirect()->route('admin.assets.index')->with('success', "{$asset->name} added.");
    }

    public function edit(CompanyAsset $asset): View
    {
        Gate::authorize('assets.manage');

        return view('admin.assets.form', ['asset' => $asset, 'admins' => $this->admins()]);
    }

    public function update(Request $request, CompanyAsset $asset): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $data = $this->validated($request);
        // A new end date gets fresh reminders.
        if (($data['expires_at'] ?? null) !== $asset->expires_at?->toDateString()) {
            $asset->reminded_days = null;
        }
        $asset->fill($data)->save();

        ActivityLog::log('company_asset_updated', 'CompanyAsset', $asset->id, ['name' => $asset->name]);

        return redirect()->route('admin.assets.index')->with('success', "{$asset->name} updated.");
    }

    /** Paid again: bought today, ends one billing cycle later. */
    public function renew(Request $request, CompanyAsset $asset): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $next = $asset->nextExpiry(Carbon::today());

        if (! $next) {
            return back()->with('error', 'One-time purchases have no renewal. Edit the dates instead.');
        }

        $asset->forceFill([
            'purchased_at' => Carbon::today(),
            'expires_at' => $next,
            'reminded_days' => null,
        ])->save();

        ActivityLog::log('company_asset_renewed', 'CompanyAsset', $asset->id, ['name' => $asset->name, 'expires_at' => $next->toDateString()]);

        return back()->with('success', "{$asset->name} renewed until {$next->format('d M Y')}.");
    }

    public function destroy(CompanyAsset $asset): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $asset->delete();

        ActivityLog::log('company_asset_deleted', 'CompanyAsset', $asset->id, ['name' => $asset->name]);

        return redirect()->route('admin.assets.index')->with('success', "{$asset->name} deleted.");
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(CompanyAsset::CATEGORIES))],
            'vendor' => ['nullable', 'string', 'max:150'],
            // Only web links: never javascript: or other schemes in a clickable link.
            'purchase_url' => ['nullable', 'url:http,https', 'max:500'],
            'account' => ['nullable', 'string', 'max:150'],
            'purchased_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:purchased_at'],
            'billing_cycle' => ['required', Rule::in(array_keys(CompanyAsset::CYCLES))],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'auto_renew' => ['sometimes', 'boolean'],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', 1)->where('is_guest', 0)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['auto_renew'] = $request->boolean('auto_renew');

        return $data;
    }

    private function admins()
    {
        return User::query()->where('is_admin', true)->where('is_guest', false)->orderBy('name')->get(['id', 'name', 'job_title']);
    }
}
