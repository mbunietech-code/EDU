<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallDevice;
use App\Services\CallDeviceService;
use App\Services\VideoHelpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Ring this computer even when signed out" on the Video help page: register
 * this browser's push subscription, test-ring a device, remove a device.
 */
class CallDeviceController extends Controller
{
    public function __construct(protected CallDeviceService $devices)
    {
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(VideoHelpService::canAnswer($request->user()), 403);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $device = $this->devices->register($request->user(), CallDevice::WEBPUSH, $data['endpoint'], $data['name'] ?? null, 'web');
        $rang = $this->devices->test($device);

        return response()->json([
            'ok' => true,
            'id' => $device->id,
            'tested' => $rang,
            'message' => $rang
                ? 'This computer is registered. A test ring was sent.'
                : 'This computer is registered, but the test ring could not be sent. Try "Test" again in a minute.',
        ]);
    }

    public function test(Request $request, CallDevice $callDevice): RedirectResponse
    {
        $this->authorizeDevice($request, $callDevice);

        $ok = $this->devices->test($callDevice);

        return back()->with($ok ? 'success' : 'error', $ok
            ? 'Test ring sent to '.($callDevice->name ?: 'the device').'. It should ring within a few seconds if it is online.'
            : 'The test ring could not be sent to '.($callDevice->name ?: 'the device').'.'.($callDevice->exists ? '' : ' It was no longer registered and has been removed.'));
    }

    public function destroy(Request $request, CallDevice $callDevice): RedirectResponse
    {
        $this->authorizeDevice($request, $callDevice);

        $callDevice->delete();

        return back()->with('success', ($callDevice->name ?: 'The device').' will no longer ring for calls.');
    }

    private function authorizeDevice(Request $request, CallDevice $device): void
    {
        $user = $request->user();
        abort_unless(
            VideoHelpService::canAnswer($user) && ((int) $device->user_id === (int) $user->id || $user->isSuperAdmin()),
            403,
        );
    }
}
