<?php

namespace App\Console\Commands;

use App\Models\GatewayPayment;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Console\Command;

/**
 * Backstop for missed callbacks: re-check pending mobile money pushes with the
 * provider and expire ones nobody answered. Runs every minute from cron.
 */
class PaymentsSyncGatewayCommand extends Command
{
    protected $signature = 'payments:sync-gateway';

    protected $description = 'Check pending mobile money payments with AzamPay / ClickPesa';

    public function handle(GatewayPaymentService $gateways): int
    {
        $checked = 0;

        GatewayPayment::where('status', 'pending')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (GatewayPayment $payment) use ($gateways, &$checked) {
                $gateways->refresh($payment);
                $checked++;
            });

        $this->info("Checked {$checked} pending payment(s).");

        return self::SUCCESS;
    }
}
