<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RejectPayment
{
    public function execute(Payment $payment, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment->update([
                'status' => 'rejected',
                'reviewed_at' => Carbon::now(config('app.timezone')),
                'reviewed_by' => auth()->id(),
                'admin_note' => $reason,
            ]);
        });
    }
}
