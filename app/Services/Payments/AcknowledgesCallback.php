<?php

namespace App\Services\Payments;

use App\Models\GatewayPayment;
use Illuminate\Http\Request;

/**
 * A gateway that expects a specific JSON reply to its callback (PesaPal IPN).
 */
interface AcknowledgesCallback
{
    public function callbackResponse(Request $request, ?GatewayPayment $payment): array;
}
