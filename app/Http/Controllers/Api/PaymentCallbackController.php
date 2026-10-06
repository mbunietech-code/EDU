<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\GatewayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment results posted by AzamPay / ClickPesa. Authenticated by the secret
 * token in the URL; ClickPesa results are re-checked with ClickPesa itself.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $gateway, string $token, GatewayPaymentService $gateways): JsonResponse
    {
        $expected = (string) config('payments.callback_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $driver = $gateways->gateway($gateway);

        if (! $driver) {
            return response()->json(['message' => 'Unknown gateway'], 404);
        }

        $payment = $gateways->handleCallback($driver, $request);

        if (! $payment) {
            Log::warning('Payment callback for unknown transaction', ['gateway' => $gateway, 'payload' => $request->all()]);
        }

        return response()->json(['received' => true]);
    }
}
