<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\VpnAccessService;
use Illuminate\Console\Command;

/**
 * Re-tries VPN activation for confirmed "vpn" orders the VPN server hasn't
 * confirmed yet (it was down, timed out, ...). The VPN side is idempotent by
 * order reference, so retrying never extends a subscription twice.
 */
class MvpnRetryActivationsCommand extends Command
{
    protected $signature = 'mvpn:retry-activations {--limit=20}';

    protected $description = 'Retry Mbunie VPN activation for paid orders not yet activated';

    public function handle(VpnAccessService $vpn): int
    {
        $pending = Order::query()
            ->where('status', 'confirmed')
            ->whereNull('vpn_activated_at')
            ->whereHas('product', fn ($q) => $q->where('type', 'vpn'))
            ->where('vpn_activation_attempts', '<', 50)
            ->orderBy('confirmed_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $ok = 0;
        foreach ($pending as $order) {
            $ok += $vpn->activateForOrder($order) ? 1 : 0;
        }

        $this->info("VPN activations: {$ok}/{$pending->count()} succeeded.");

        return self::SUCCESS;
    }
}
