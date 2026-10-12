<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Product;
use RuntimeException;

/**
 * The VPN's plans (EduHub admin → Mbunie VPN → Plans) are the one place VPN
 * prices live. This copies them onto the plans of every EduHub product of
 * type "vpn": name, days, TZS price, on sale or not. A VPN plan with no TZS
 * price, or switched off, is not sold here; EduHub VPN plans whose code the
 * VPN no longer has are switched off (never deleted: old orders keep them).
 * The product's own price (shown on catalogue cards) follows its cheapest plan.
 */
class VpnPlanSync
{
    public function __construct(private MvpnClient $vpn) {}

    /**
     * @return array{updated:int, created:int, disabled:int}
     *
     * @throws RuntimeException when the VPN can't be reached
     */
    public function sync(): array
    {
        $vpnPlans = collect($this->vpn->admin('GET', 'plans')['data'] ?? [])->keyBy('code');
        $stats = ['updated' => 0, 'created' => 0, 'disabled' => 0];

        foreach (Product::where('type', 'vpn')->get() as $product) {
            foreach ($vpnPlans as $code => $vp) {
                $plan = Plan::firstOrNew(['product_id' => $product->id, 'mvpn_plan_code' => $code]);
                $plan->fill([
                    'name' => $vp['name'],
                    'duration_type' => 'days',
                    'duration_days' => (int) $vp['days'],
                    'price' => (float) $vp['price_tzs'],
                    'status' => $vp['is_active'] && $vp['price_tzs'] > 0 ? 'active' : 'inactive',
                    'sort_order' => (int) $vp['days'],
                ]);
                if (! $plan->exists) {
                    $plan->description = $vp['max_devices'].' '.($vp['max_devices'] === 1 ? 'device' : 'devices');
                    $plan->save();
                    $stats['created']++;
                } elseif ($plan->isDirty()) {
                    $plan->save();
                    $stats['updated']++;
                }
            }

            $stats['disabled'] += Plan::where('product_id', $product->id)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('mvpn_plan_code')->orWhereNotIn('mvpn_plan_code', $vpnPlans->keys()))
                ->update(['status' => 'inactive']);

            // Catalogue cards show the product price: the cheapest plan on sale.
            $from = (float) (Plan::where('product_id', $product->id)->where('status', 'active')->min('price') ?? 0);
            if ((float) $product->price !== $from) {
                $product->forceFill(['price' => $from])->save();
                $stats['updated']++;
            }
        }

        if (array_sum($stats) > 0) {
            ActivityLog::log('vpn_plans_synced', 'Plan', null, $stats);
        }

        return $stats;
    }
}
