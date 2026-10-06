<?php

namespace App\Services\Payments;

use App\Models\ActivityLog;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GatewayPaymentService
{
    /** @var array<string, MobileMoneyGateway> */
    protected array $gateways;

    public function __construct(
        AzamPayGateway $azamPay,
        ClickPesaGateway $clickPesa,
        PayPalGateway $payPal,
        protected PaymentApprovalService $approvals,
        protected NotificationService $notifications,
    ) {
        $this->gateways = [
            $azamPay->key() => $azamPay,
            $clickPesa->key() => $clickPesa,
            $payPal->key() => $payPal,
        ];
    }

    /**
     * @return array<string, MobileMoneyGateway>
     */
    public function enabled(): array
    {
        return array_filter($this->gateways, fn (MobileMoneyGateway $gateway) => $gateway->isEnabled());
    }

    /**
     * Enabled gateways that send a USSD push to the customer's phone.
     *
     * @return array<string, MobileMoneyGateway>
     */
    public function mobile(): array
    {
        return array_filter($this->enabled(), fn (MobileMoneyGateway $gateway) => ! $gateway->usesRedirect());
    }

    public function gateway(string $key): ?MobileMoneyGateway
    {
        return $this->gateways[$key] ?? null;
    }

    /**
     * Normalise a Tanzanian mobile number to 2557XXXXXXXX / 2556XXXXXXXX.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        return preg_match('/^255[67]\d{8}$/', $digits) ? $digits : null;
    }

    /**
     * Unique reference sent to the provider: letters and digits only, at most
     * 20 characters (the mobile money limit ClickPesa enforces).
     */
    public static function externalId(Order $order): string
    {
        $prefix = 'MH'.$order->id.'T';

        return $prefix.strtoupper(Str::random(max(4, 20 - strlen($prefix))));
    }

    /**
     * A push for this order that is still waiting for the customer's PIN.
     */
    public function activeFor(Order $order): ?GatewayPayment
    {
        return GatewayPayment::where('order_id', $order->id)
            ->where('status', 'pending')
            ->where('gateway', '!=', 'paypal')
            ->where('created_at', '>=', now()->subMinutes($this->timeoutMinutes()))
            ->latest('id')
            ->first();
    }

    /**
     * @throws GatewayException
     */
    public function start(Order $order, User $user, MobileMoneyGateway $gateway, string $phone, ?string $network): GatewayPayment
    {
        $payment = GatewayPayment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => $gateway->key(),
            'network' => $network,
            'phone' => $phone,
            'amount' => $order->amount,
            'currency' => 'TZS',
            'external_id' => self::externalId($order),
            'status' => 'pending',
        ]);

        try {
            $transactionId = $gateway->initiate($payment);
        } catch (GatewayException $e) {
            $payment->update(['status' => 'failed', 'message' => Str::limit($e->getMessage(), 250), 'completed_at' => now()]);

            throw $e;
        }

        $payment->update(['provider_transaction_id' => $transactionId]);

        ActivityLog::log('gateway_payment_started', 'Order', $order->id, [
            'gateway' => $gateway->key(),
            'gateway_payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);

        return $payment;
    }

    /**
     * Handle a provider callback. Returns the gateway payment it applied to.
     */
    public function handleCallback(MobileMoneyGateway $gateway, Request $request): ?GatewayPayment
    {
        $data = $gateway->parseCallback($request);

        if (blank($data['external_id'])) {
            return null;
        }

        $payment = GatewayPayment::where('gateway', $gateway->key())
            ->where('external_id', $data['external_id'])
            ->first();

        if (! $payment) {
            return null;
        }

        $payment->update(['callback_payload' => Str::limit(json_encode($request->all()), 60000, '')]);

        if ($data['verify']) {
            $this->refresh($payment, force: true);
        } elseif ($data['status'] === 'success') {
            $this->markSuccessful($payment, $data['reference'], $data['amount']);
        } elseif ($data['status'] === 'failed') {
            $this->markFailed($payment, $request->input('message') ?: 'The payment was not completed.');
        }

        return $payment->fresh();
    }

    /**
     * Ask the provider for the latest result, and expire pushes nobody answered.
     */
    /**
     * $force skips the polling budget; used when a callback arrives.
     */
    public function refresh(GatewayPayment $payment, bool $force = false): GatewayPayment
    {
        if ($payment->isFinal()) {
            return $payment;
        }

        $result = null;

        if ($force || $this->dueForCheck($payment)) {
            $result = $this->gateway($payment->gateway)?->fetchStatus($payment);
            $payment->update(['last_checked_at' => now()]);
        }

        if ($result && $result['status'] === 'success') {
            $this->markSuccessful($payment, $result['reference'], $result['amount']);
        } elseif ($result && $result['status'] === 'failed') {
            $this->markFailed($payment, $result['message'] ?? 'The payment was declined or cancelled.');
        } elseif ($payment->isPending() && $payment->created_at->lt(now()->subMinutes($this->timeoutMinutes($payment->gateway)))) {
            // Still accept a late success callback after this.
            $payment->update(['status' => 'expired', 'message' => 'No confirmation received in time.']);
        }

        return $payment->fresh();
    }

    public function markSuccessful(GatewayPayment $gatewayPayment, ?string $reference, ?float $paidAmount): void
    {
        $result = DB::transaction(function () use ($gatewayPayment, $reference, $paidAmount) {
            $locked = GatewayPayment::whereKey($gatewayPayment->id)->lockForUpdate()->first();

            if ($locked->isSuccessful()) {
                return null;
            }

            $order = $locked->order()->lockForUpdate()->first();
            $amount = $paidAmount ?? (float) $locked->amount;
            $fullyPaid = $amount + 0.009 >= (float) $order->amount;
            $canAutoApprove = $fullyPaid && $order->isPending() && ! $order->payments()->where('status', 'approved')->exists();

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $locked->user_id,
                'amount' => $amount,
                'payment_method' => $locked->gateway,
                'transaction_reference' => $reference ?: $locked->provider_transaction_id ?: $locked->external_id,
                'status' => 'pending',
                'admin_note' => $canAutoApprove ? null : ($fullyPaid
                    ? 'Paid through '.$locked->gateway.' but the order was no longer awaiting payment. Check for a double payment.'
                    : 'Paid through '.$locked->gateway.' but the amount is less than the order total.'),
            ]);

            $locked->update([
                'status' => 'success',
                'payment_id' => $payment->id,
                'provider_reference' => $reference,
                'message' => null,
                'completed_at' => now(),
            ]);

            return [$payment, $canAutoApprove];
        });

        if (! $result) {
            return;
        }

        [$payment, $canAutoApprove] = $result;

        ActivityLog::log('gateway_payment_succeeded', 'Payment', $payment->id, [
            'gateway' => $gatewayPayment->gateway,
            'reference' => $payment->transaction_reference,
            'auto_approved' => $canAutoApprove,
        ]);

        if ($canAutoApprove) {
            $this->approve($payment);
        }

        // Money was received either way: receipt to the customer, heads-up to admins.
        $payment->refresh();
        $gatewayPayment->refresh();
        $this->notifications->notifyPaymentReceipt($payment, $gatewayPayment);
        $this->notifications->notifyAdminsOnlinePayment($payment, $gatewayPayment);
    }

    public function markFailed(GatewayPayment $payment, string $message): void
    {
        GatewayPayment::whereKey($payment->id)
            ->whereIn('status', ['pending', 'expired'])
            ->update(['status' => 'failed', 'message' => Str::limit($message, 250), 'completed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Approve exactly like an admin would, including customer notifications.
     */
    protected function approve(Payment $payment): void
    {
        try {
            $this->approvals->approve($payment);
        } catch (\RuntimeException $e) {
            // Paid, but delivery needs an admin (e.g. no shared account free).
            report($e);
            $payment->update(['admin_note' => 'Paid online, but automatic delivery failed: '.Str::limit($e->getMessage(), 200)]);
            if ($payment->order->product) {
                $this->notifications->notifyAdminAccountUnavailable($payment->order->product);
            }

            return;
        }

        $payment->refresh();
        $order = $payment->order;

        // The receipt email replaces the generic "payment approved" message.
        if ($order->isToolOrder()) {
            $this->notifications->notifyToolKeyDelivered($payment->user, $order);
        } elseif ($order->isSoftware()) {
            $this->notifications->notifySoftwareDelivered($payment->user, $order);
        } elseif ($order->subscription) {
            $this->notifications->notifySubscriptionActivated($payment->user, $order->subscription);
        }
    }

    /**
     * Respect each provider's API budget (ClickPesa allows 100 calls a day
     * before KYC): wait a little after the push, then check every
     * poll_seconds. Callbacks bypass this and arrive instantly.
     */
    protected function dueForCheck(GatewayPayment $payment): bool
    {
        $interval = max(5, (int) config("payments.gateways.{$payment->gateway}.poll_seconds", 10));

        if ($payment->created_at->gt(now()->subSeconds(min($interval, 20)))) {
            return false;
        }

        return ! $payment->last_checked_at || $payment->last_checked_at->lte(now()->subSeconds($interval));
    }

    public function timeoutMinutes(?string $gateway = null): int
    {
        $minutes = $gateway ? config("payments.gateways.{$gateway}.timeout_minutes") : null;

        return max(1, (int) ($minutes ?? config('payments.pending_timeout_minutes', 10)));
    }
}
