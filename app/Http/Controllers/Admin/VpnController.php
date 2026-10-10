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
            $error = 'MVPN_PARTNER_SECRET haijawekwa kwenye .env ya EduHub.';
        } else {
            try {
                $stats = $mvpn->stats();
            } catch (RuntimeException $e) {
                $error = 'Haikuweza kufikia VPN server: '.$e->getMessage();
            }
        }

        $orders = Order::with(['user', 'plan'])
            ->whereHas('product', fn ($q) => $q->where('type', 'vpn'))
            ->latest()
            ->limit(30)
            ->get();

        $failed = $orders->filter(fn (Order $o) => $o->status === 'confirmed' && ! $o->vpn_activated_at);

        return view('admin.vpn.index', compact('stats', 'error', 'orders', 'failed'));
    }

    public function retry(Order $order, VpnAccessService $vpn): RedirectResponse
    {
        abort_unless($order->product?->isVpn(), 404);

        ActivityLog::log('vpn_activation_retry', 'Order', $order->id);
        $ok = $vpn->activateForOrder($order);

        return back()->with($ok ? 'success' : 'error', $ok
            ? "VPN imewashwa kwa oda #{$order->order_number}."
            : 'Bado imeshindwa: '.($order->fresh()->vpn_activation_error ?? 'kosa lisilojulikana'));
    }
}
