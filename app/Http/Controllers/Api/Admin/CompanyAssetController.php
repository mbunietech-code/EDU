<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CompanyAsset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Company assets for the MHub app: the same list, filters and actions as
 * admin/assets on the web (assets.view to see, assets.manage to change).
 */
class CompanyAssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['all', 'attention', 'expired', 'soon', 'active', 'no_expiry'], true)
            ? (string) $request->query('status') : 'all';

        $assets = CompanyAsset::query()
            ->with('responsible:id,name,job_title')
            ->listFilter($status, (string) $request->query('category', ''), trim((string) $request->query('q', '')))
            ->paginate(25);

        return response()->json([
            'data' => collect($assets->items())->map(fn (CompanyAsset $a) => $this->row($a))->values(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'total' => $assets->total(),
                'counts' => CompanyAsset::counts(),
                'categories' => CompanyAsset::CATEGORIES,
                'cycles' => CompanyAsset::CYCLES,
                'soon_days' => CompanyAsset::SOON_DAYS,
                'can_manage' => (bool) $request->user()?->can('assets.manage'),
            ],
        ]);
    }

    /** Form choices: categories, billing cycles and the admins who can look after an asset. */
    public function options(): JsonResponse
    {
        return response()->json([
            'categories' => CompanyAsset::CATEGORIES,
            'cycles' => CompanyAsset::CYCLES,
            'admins' => CompanyAsset::admins()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'job_title' => $u->job_title,
            ])->values(),
        ]);
    }

    public function show(CompanyAsset $asset): JsonResponse
    {
        $asset->load(['responsible:id,name,job_title', 'creator:id,name']);

        return response()->json(['data' => $this->row($asset) + [
            'creator_name' => $asset->creator?->name,
            'created_at' => optional($asset->created_at)->toIso8601String(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('assets.manage');

        $asset = CompanyAsset::create(CompanyAsset::validatedFrom($request) + ['created_by' => $request->user()->id]);
        ActivityLog::log('company_asset_created', 'CompanyAsset', $asset->id, ['name' => $asset->name]);

        return response()->json(['message' => "{$asset->name} added.", 'data' => ['id' => $asset->id]], 201);
    }

    public function update(Request $request, CompanyAsset $asset): JsonResponse
    {
        $this->authorize('assets.manage');

        $data = CompanyAsset::validatedFrom($request);
        // A new end date gets fresh reminders.
        if (($data['expires_at'] ?? null) !== $asset->expires_at?->toDateString()) {
            $asset->reminded_days = null;
        }
        $asset->fill($data)->save();
        ActivityLog::log('company_asset_updated', 'CompanyAsset', $asset->id, ['name' => $asset->name]);

        return response()->json(['message' => "{$asset->name} updated."]);
    }

    /** Paid again: bought today, ends one billing cycle later. */
    public function renew(CompanyAsset $asset): JsonResponse
    {
        $this->authorize('assets.manage');

        $next = $asset->nextExpiry(Carbon::today());
        if (! $next) {
            return response()->json(['message' => 'One-time purchases have no renewal. Edit the dates instead.'], 422);
        }

        $asset->forceFill([
            'purchased_at' => Carbon::today(),
            'expires_at' => $next,
            'reminded_days' => null,
        ])->save();
        ActivityLog::log('company_asset_renewed', 'CompanyAsset', $asset->id, ['name' => $asset->name, 'expires_at' => $next->toDateString()]);

        return response()->json(['message' => "{$asset->name} renewed until {$next->format('d M Y')}."]);
    }

    public function destroy(CompanyAsset $asset): JsonResponse
    {
        $this->authorize('assets.manage');

        $asset->delete();
        ActivityLog::log('company_asset_deleted', 'CompanyAsset', $asset->id, ['name' => $asset->name]);

        return response()->json(['message' => "{$asset->name} deleted."]);
    }

    private function row(CompanyAsset $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'category' => $a->category,
            'category_label' => $a->categoryLabel(),
            'vendor' => $a->vendor,
            'purchase_url' => $a->purchase_url,
            'account' => $a->account,
            'purchased_at' => optional($a->purchased_at)->toDateString(),
            'expires_at' => optional($a->expires_at)->toDateString(),
            'days_left' => $a->daysLeft(),
            'state' => $a->state(),
            'billing_cycle' => $a->billing_cycle,
            'cycle_label' => $a->cycleLabel(),
            'cost' => $a->cost !== null ? (float) $a->cost : null,
            'currency' => $a->currency,
            'auto_renew' => (bool) $a->auto_renew,
            'responsible_id' => $a->responsible_id,
            'responsible_name' => $a->responsible?->name,
            'responsible_job_title' => $a->responsible?->job_title,
            'notes' => $a->notes,
            'can_renew' => $a->billing_cycle !== 'one_time',
        ];
    }
}
