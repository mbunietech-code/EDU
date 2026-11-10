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
        $assets = CompanyAsset::query()
            ->with('responsible:id,name,job_title')
            ->listFilter($status, $category, $search)
            ->paginate(25)
            ->withQueryString();

        $counts = CompanyAsset::counts();

        return view('admin.assets.index', compact('assets', 'counts', 'status', 'category', 'search'));
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
        return CompanyAsset::validatedFrom($request);
    }

    private function admins()
    {
        return CompanyAsset::admins();
    }
}
