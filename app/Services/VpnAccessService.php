<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Activates a confirmed "vpn" order on the Mbunie VPN control plane.
 * Safe to call repeatedly: the VPN side is idempotent by order reference,
 * so a retry after a timeout never adds the plan's days twice.
 */
class VpnAccessService
{
    public function __construct(private MvpnClient $mvpn) {}

    public static function reference(Order $order): string
    {
        return 'eduhub-order-'.$order->id;
    }

    /** @return bool true when the VPN confirmed the activation */
    public function activateForOrder(Order $order): bool
    {
        $order->loadMissing(['user', 'plan', 'product']);

        if ($order->vpn_activated_at !== null) {
            return true;
        }
        if ($order->status !== 'confirmed') {
            return false;
        }

        $planCode = $order->plan?->mvpn_plan_code;
        if (! $planCode) {
            return $this->fail($order, 'Plan has no MVPN plan code configured.');
        }

        try {
            $res = $this->mvpn->activate([
                'reference' => self::reference($order),
                'eduhub_user_id' => $order->user_id,
                'email' => $order->user->email,
                'name' => $order->user->name,
                'email_verified' => $order->user->email_verified_at !== null,
                'plan_code' => $planCode,
                'amount_cents' => (int) round((float) $order->amount * 100),
                'currency' => 'tzs',
            ]);
        } catch (RuntimeException $e) {
            return $this->fail($order, $e->getMessage());
        }

        $expires = $res['subscription']['expires_at'] ?? null;
        $order->forceFill([
            'vpn_activated_at' => now(),
            'vpn_expires_at' => $expires ? \Illuminate\Support\Carbon::parse($expires) : null,
            'vpn_activation_error' => null,
        ])->save();

        ActivityLog::log('vpn_activated', 'Order', $order->id, [
            'plan' => $planCode,
            'expires_at' => $expires,
            'duplicate' => (bool) ($res['duplicate'] ?? false),
        ]);

        return true;
    }

    private function fail(Order $order, string $message): bool
    {
        $order->forceFill([
            'vpn_activation_attempts' => $order->vpn_activation_attempts + 1,
            'vpn_activation_error' => mb_substr($message, 0, 500),
        ])->save();

        Log::warning("VPN activation failed for order {$order->id}: {$message}");
        ActivityLog::log('vpn_activation_failed', 'Order', $order->id, ['error' => $message]);

        return false;
    }
}
