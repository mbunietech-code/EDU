<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApprovePayment
{
    public function execute(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => 'approved',
                'reviewed_at' => Carbon::now(config('app.timezone')),
                'reviewed_by' => auth()->id(),
            ]);
        });
    }
}
