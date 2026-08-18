<?php

namespace App\Services;

use App\Actions\Payments\RejectPayment;
use App\Actions\Payments\ApprovePayment;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class PaymentApprovalService
{
    protected ApprovePayment $approvePayment;
    protected RejectPayment $rejectPayment;
    protected SubscriptionService $subscriptionService;
    protected SoftwareAccessService $softwareAccessService;

    public function __construct(
        ApprovePayment $approvePayment,
        RejectPayment $rejectPayment,
        SubscriptionService $subscriptionService,
        SoftwareAccessService $softwareAccessService
    ) {
        $this->approvePayment = $approvePayment;
        $this->rejectPayment = $rejectPayment;
        $this->subscriptionService = $subscriptionService;
        $this->softwareAccessService = $softwareAccessService;
    }

    public function approve(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->approvePayment->execute($payment);

            $order = $payment->order;
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            $key = null;

            if ($order->product->isSoftware()) {
                $key = $this->softwareAccessService->activateForOrder($order);
            } else {
                $this->subscriptionService->createFromOrder($order);
            }

            \App\Models\ActivityLog::log(
                'payment_approved',
                'Payment',
                $payment->id,
                [
                    'order_id' => $order->id,
                    'subscription_id' => $order->subscription?->id,
                    'product_key_id' => $key?->id,
                ]
            );
        });
    }

    public function reject(Payment $payment, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $this->rejectPayment->execute($payment, $reason);

            \App\Models\ActivityLog::log(
                'payment_rejected',
                'Payment',
                $payment->id,
                ['order_id' => $payment->order_id, 'reason' => $reason]
            );
        });
    }
}
