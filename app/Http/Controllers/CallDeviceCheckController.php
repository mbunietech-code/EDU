<?php

namespace App\Http\Controllers;

use App\Services\CallDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Asked by public/call-sw.js when an (empty) Web Push wakes a registered
 * computer: what is ringing? No session: the push endpoint identifies the device.
 */
class CallDeviceCheckController extends Controller
{
    public function __invoke(Request $request, CallDeviceService $devices): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint', '');
        $result = $endpoint !== '' && mb_strlen($endpoint) <= 2000
            ? rescue(fn () => $devices->check($endpoint), null)
            : null;

        if ($result === null) {
            return response()->json(['message' => 'Unknown device.'], 404);
        }

        return response()->json($result);
    }
}
