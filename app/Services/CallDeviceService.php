<?php

namespace App\Services;

use App\Models\CallDevice;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\VideoHelpRequest;
use Illuminate\Support\Collection;

/**
 * Call devices ring for video help calls without anyone signed in on them:
 * a browser (empty Web Push, then public/call-sw.js asks check() what is
 * ringing) or a phone with the MHub app (FCM on the "mhub_calls" channel).
 * Only devices of admins who may still answer (VideoHelpService::canAnswer)
 * are rung; answering always needs a sign-in.
 */
class CallDeviceService
{
    public function __construct(
        protected WebPushService $webPush,
        protected FcmService $fcm,
    ) {
    }

    /** Register (or move to $user) this browser / phone. */
    public function register(User $user, string $channel, string $token, ?string $name, ?string $platform): CallDevice
    {
        return CallDevice::updateOrCreate(
            ['token_hash' => CallDevice::hashToken($token)],
            [
                'user_id' => $user->id,
                'channel' => $channel,
                'token' => $token,
                'name' => $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 120) : null,
                'platform' => $platform,
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * Ring every call device of the admins in $answerers.
     *
     * @param  Collection<int,User>  $answerers
     * @return int devices the push service accepted
     */
    public function ring(VideoHelpRequest $call, Collection $answerers, int $ttl): int
    {
        $ids = $answerers->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        $devices = CallDevice::query()->whereIn('user_id', $ids)->get();
        if ($devices->isEmpty()) {
            return 0;
        }

        // A signed-in phone already gets this call through its own device token (PushDatabaseNotification).
        $signedIn = DeviceToken::query()->whereIn('user_id', $ids)->pluck('token')->flip();

        $name = $call->user?->name ?? 'A member';
        $sent = 0;

        foreach ($devices as $device) {
            if ($device->channel === CallDevice::FCM && $signedIn->has($device->token)) {
                continue;
            }

            $status = $device->channel === CallDevice::WEBPUSH
                ? $this->webPush->send($device->token, $ttl)
                : $this->fcm->send($device->token, 'Video help call', $name.' is calling for video help. Open MHub to answer.', [
                    'type' => 'video_help_call',
                    'url' => route('admin.video-help.index'),
                ], ['channel' => 'mhub_calls', 'ttl' => $ttl]);

            $sent += $this->record($device, $status) ? 1 : 0;
        }

        return $sent;
    }

    /** "Test ring" from the Video help page. */
    public function test(CallDevice $device): bool
    {
        if ($device->channel === CallDevice::WEBPUSH) {
            $device->forceFill(['test_until' => now()->addMinutes(2)])->save();
            $status = $this->webPush->send($device->token, 120);
        } else {
            $status = $this->fcm->send($device->token, 'Test ring: MbunieEduHub', 'This phone will ring when a customer calls for video help.', [
                'type' => 'video_help_test',
            ], ['channel' => 'mhub_calls', 'ttl' => 120]);
        }

        return $this->record($device, $status);
    }

    /**
     * What a woken browser shows (public/call-sw.js). The push endpoint is the
     * device's secret; unknown endpoints get nothing.
     *
     * @return array{test:bool,calls:array<int,array{id:int,name:string,reason:?string}>,url:string}|null
     */
    public function check(string $endpoint): ?array
    {
        $device = CallDevice::findByToken($endpoint, CallDevice::WEBPUSH);
        if (! $device) {
            return null;
        }

        $test = $device->test_until !== null && $device->test_until->isFuture();
        $device->forceFill(['last_seen_at' => now(), 'test_until' => null])->save();

        $owner = $device->user;
        $calls = [];
        if (VideoHelpService::canAnswer($owner)) {
            $calls = VideoHelpRequest::query()
                ->ringing()
                ->where('ring_until', '>', now())
                ->with('user:id,name')
                ->orderBy('id')
                ->limit(5)
                ->get()
                ->reject(fn (VideoHelpRequest $c) => in_array((int) $owner->id, $c->declinedIds(), true))
                ->map(fn (VideoHelpRequest $c) => [
                    'id' => $c->id,
                    'name' => $c->user?->name ?? 'A member',
                    'reason' => $c->reason ? mb_strimwidth($c->reason, 0, 120, '...') : null,
                ])
                ->values()
                ->all();
        }

        // A real call always wins over a pending test ring.
        return ['test' => $test && $calls === [], 'calls' => $calls, 'url' => route('admin.video-help.index')];
    }

    /** Remember the outcome; forget devices the push service says are gone. */
    private function record(CallDevice $device, ?int $status): bool
    {
        $gone = $device->channel === CallDevice::WEBPUSH
            ? in_array($status, [404, 410], true)
            : FcmService::isGone($status);

        if ($gone) {
            $device->delete();

            return false;
        }

        $ok = $status !== null && $status >= 200 && $status < 300;
        if ($ok) {
            $device->forceFill(['last_rung_at' => now()])->save();
        }

        return $ok;
    }
}
