<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallDevice;
use App\Services\CallDeviceService;
use App\Services\VideoHelpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MHub app, support admins only: "Ring this phone for video help calls, even
 * when signed out". The phone's FCM token is kept as a call device, so signing
 * out (which removes the normal device token) does not stop it ringing.
 */
class CallDeviceController extends Controller
{
    public function __construct(protected CallDeviceService $devices)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:512']]);

        $device = CallDevice::findByToken($request->query('token'), CallDevice::FCM);

        return response()->json([
            'can_answer' => VideoHelpService::canAnswer($request->user()),
            'registered' => $device !== null && (int) $device->user_id === (int) $request->user()->id,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(VideoHelpService::canAnswer($request->user()), 403, 'Only support admins can receive video help calls.');

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $device = $this->devices->register($request->user(), CallDevice::FCM, $data['token'], $data['device_name'] ?? null, $data['platform'] ?? null);

        return response()->json(['registered' => true, 'tested' => $this->devices->test($device)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:512']]);

        CallDevice::query()
            ->where('token_hash', CallDevice::hashToken($request->input('token')))
            ->where('channel', CallDevice::FCM)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['registered' => false]);
    }
}
