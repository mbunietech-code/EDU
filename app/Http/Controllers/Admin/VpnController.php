<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\MvpnClient;
use App\Services\VpnAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * Mbunie VPN overview inside the EduHub admin: live numbers come from the VPN
 * control plane (signed partner API); VPN orders sold here come from our DB.
 */
class VpnController extends Controller
{
    public function index(MvpnClient $mvpn): View
    {
        $stats = null;
        $error = null;

        if (! $mvpn->configured()) {
            $error = 'MVPN_PARTNER_SECRET is not set in the EduHub .env.';
        } else {
            try {
                $stats = $mvpn->stats();
            } catch (RuntimeException $e) {
                $error = 'Could not reach the VPN server: '.$e->getMessage();
            }
        }

        $orders = Order::with(['user', 'plan'])
            ->whereHas('product', fn ($q) => $q->where('type', 'vpn'))
            ->latest()
            ->limit(30)
            ->get();

        $failed = $orders->filter(fn (Order $o) => $o->status === 'confirmed' && ! $o->vpn_activated_at);

        // 1 TZS in USD / CNY, to add up revenue taken in different currencies.
        $rates = app(\App\Services\CurrencyRateService::class)->rates();

        return view('admin.vpn.index', compact('stats', 'error', 'orders', 'failed', 'rates'));
    }

    public function retry(Order $order, VpnAccessService $vpn): RedirectResponse
    {
        abort_unless($order->product?->isVpn(), 404);

        ActivityLog::log('vpn_activation_retry', 'Order', $order->id);
        $ok = $vpn->activateForOrder($order);

        return back()->with($ok ? 'success' : 'error', $ok
            ? "VPN activated for order #{$order->order_number}."
            : 'Still failing: '.($order->fresh()->vpn_activation_error ?? 'unknown error'));
    }
}
