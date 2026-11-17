<?php

namespace Tests\Feature;

use App\Models\CallDevice;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\VideoHelpRequest;
use App\Services\FcmService;
use App\Services\VideoHelpService;
use App\Services\WebPushService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Call devices: support admins' computers (Web Push) and phones (FCM) ring
 * for video help calls even while nobody is signed in on them.
 */
class CallDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/test-endpoint-123';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withoutDefer();
        // Read per request, so a test can make the push service answer differently later.
        Http::fake(['fcm.googleapis.com/fcm/send/*' => fn () => Http::response('', $this->pushStatus)]);
    }

    private int $pushStatus = 201;

    private function admin(array $permissions = ['video_help.answer']): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    private function registerBrowser(User $admin, string $endpoint = self::ENDPOINT): CallDevice
    {
        $this->actingAs($admin)
            ->postJson(route('admin.video-help.devices.store'), ['endpoint' => $endpoint, 'name' => 'Office PC'])
            ->assertOk()
            ->assertJsonPath('tested', true);

        return CallDevice::findByToken($endpoint);
    }

    public function test_the_vapid_token_is_a_valid_es256_signature(): void
    {
        $push = app(WebPushService::class);
        $this->assertSame(87, strlen((string) $push->publicKey())); // 65-byte P-256 point, base64url

        $this->assertSame(201, $push->send(self::ENDPOINT, 60));

        Http::assertSent(function (HttpRequest $request) use ($push) {
            preg_match('/^vapid t=([^,]+), k=(.+)$/', $request->header('Authorization')[0] ?? '', $m);
            [$h, $p, $s] = explode('.', $m[1]);
            $claims = json_decode(base64_decode(strtr($p, '-_', '+/')), true);

            return $m[2] === $push->publicKey()
                && $claims['aud'] === 'https://fcm.googleapis.com'
                && $request->header('TTL')[0] === '60'
                && $request->header('Urgency')[0] === 'high'
                && $this->verifyEs256("$h.$p", $s, $push->publicKey());
        });
    }

    public function test_a_support_admin_registers_this_computer_and_it_is_test_rung(): void
    {
        $admin = $this->admin();
        $device = $this->registerBrowser($admin);

        $this->assertSame(CallDevice::WEBPUSH, $device->channel);
        $this->assertSame('Office PC', $device->name);
        $this->assertNotNull($device->last_rung_at);

        // The woken service worker asks what to show: the test ring, once.
        $this->postJson(route('call-devices.check'), ['endpoint' => self::ENDPOINT])
            ->assertOk()->assertJsonPath('test', true)->assertJsonPath('calls', []);
        $this->postJson(route('call-devices.check'), ['endpoint' => self::ENDPOINT])
            ->assertOk()->assertJsonPath('test', false);

        $this->get(route('admin.video-help.index'))->assertOk()->assertSee('Office PC')->assertSee('Devices that ring for calls');
    }

    public function test_admins_who_cannot_answer_cannot_register(): void
    {
        $this->actingAs($this->admin(['orders.view']))
            ->postJson(route('admin.video-help.devices.store'), ['endpoint' => self::ENDPOINT])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->postJson(route('admin.video-help.devices.store'), ['endpoint' => 'http://insecure.example/x'])
            ->assertUnprocessable();

        $this->assertSame(0, CallDevice::count());
    }

    public function test_a_call_rings_registered_computers_after_sign_out_and_shows_the_caller(): void
    {
        $admin = $this->admin();
        $this->registerBrowser($admin);
        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();

        $member = User::factory()->create(['name' => 'Juma Mteja']);
        $call = app(VideoHelpService::class)->request($member, 'Plan yangu haifanyi kazi');

        Http::assertSentCount(2); // the test ring + this call
        $this->postJson(route('call-devices.check'), ['endpoint' => self::ENDPOINT])
            ->assertOk()
            ->assertJsonPath('calls.0.id', $call->id)
            ->assertJsonPath('calls.0.name', 'Juma Mteja')
            ->assertJsonPath('calls.0.reason', 'Plan yangu haifanyi kazi');

        // Once answered, a late wake-up shows nothing ringing.
        $call->forceFill(['status' => VideoHelpRequest::ACCEPTED])->save();
        $this->postJson(route('call-devices.check'), ['endpoint' => self::ENDPOINT])->assertOk()->assertJsonPath('calls', []);
    }

    public function test_devices_of_admins_who_lost_the_permission_do_not_ring(): void
    {
        $admin = $this->admin();
        $this->registerBrowser($admin);
        $admin->forceFill(['permissions' => ['orders.view']])->save();

        app(VideoHelpService::class)->request(User::factory()->create(), null);

        Http::assertSentCount(1); // only the test ring
        $this->postJson(route('call-devices.check'), ['endpoint' => self::ENDPOINT])->assertOk()->assertJsonPath('calls', []);
    }

    public function test_unknown_endpoints_get_nothing_and_gone_browsers_are_removed(): void
    {
        $this->postJson(route('call-devices.check'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/nope'])->assertNotFound();

        $admin = $this->admin();
        $device = $this->registerBrowser($admin);

        $this->pushStatus = 410;
        $this->post(route('admin.video-help.devices.test', $device))->assertRedirect();

        $this->assertModelMissing($device);
    }

    public function test_only_the_owner_or_a_super_admin_can_test_or_remove_a_device(): void
    {
        $device = $this->registerBrowser($this->admin());

        $this->actingAs($this->admin())->delete(route('admin.video-help.devices.destroy', $device))->assertForbidden();

        $super = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $this->actingAs($super)->delete(route('admin.video-help.devices.destroy', $device))->assertRedirect();
        $this->assertModelMissing($device);
    }

    public function test_the_phone_app_keeps_ringing_after_sign_out_without_double_pushes(): void
    {
        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')
            ->withArgs(fn ($token, $title, $body, $data, $options) => $token === 'phone-token' && ($options['channel'] ?? null) === 'mhub_calls')
            ->twice() // the test ring at registration + the call while signed out
            ->andReturn(200);

        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->postJson('/api/device-tokens', ['token' => 'phone-token', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/call-devices', ['token' => 'phone-token', 'platform' => 'android', 'device_name' => 'Pixel'])
            ->assertOk()->assertJsonPath('registered', true);
        $this->getJson('/api/call-devices?token=phone-token')->assertJsonPath('registered', true)->assertJsonPath('can_answer', true);

        // Still signed in: the normal device token already carries the call, no second push.
        app(VideoHelpService::class)->request(User::factory()->create(), null);

        // Sign out removes the normal token; the call device stays and rings.
        $this->deleteJson('/api/device-tokens', ['token' => 'phone-token'])->assertOk();
        $this->assertSame(0, DeviceToken::count());
        app(VideoHelpService::class)->request(User::factory()->create(), null);

        $this->deleteJson('/api/call-devices', ['token' => 'phone-token'])->assertOk();
        $this->assertSame(0, CallDevice::count());
    }

    public function test_members_cannot_register_a_phone_for_calls(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/call-devices', ['token' => 'phone-token'])->assertForbidden();
        $this->getJson('/api/call-devices?token=phone-token')->assertOk()->assertJsonPath('can_answer', false);
    }

    private function verifyEs256(string $input, string $sig, string $publicB64): bool
    {
        $raw = base64_decode(strtr($sig, '-_', '+/').str_repeat('=', (4 - strlen($sig) % 4) % 4));
        $int = function (string $b) {
            $b = ltrim($b, "\0");
            if ($b === '' || ord($b[0]) & 0x80) {
                $b = "\0".$b;
            }

            return "\x02".chr(strlen($b)).$b;
        };
        $der = $int(substr($raw, 0, 32)).$int(substr($raw, 32));
        $der = "\x30".chr(strlen($der)).$der;

        $point = base64_decode(strtr($publicB64, '-_', '+/').'=');
        // SubjectPublicKeyInfo for an uncompressed P-256 point.
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";

        return openssl_verify($input, $der, $pem, OPENSSL_ALGO_SHA256) === 1;
    }
}
