<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\LearningRoom;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppUserGapsTest extends TestCase
{
    use RefreshDatabase;

    // --- #1 Research tools ---------------------------------------------------

    public function test_user_can_order_a_research_tool_from_the_app(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $tool = Tool::create(['name' => 'EndNote', 'slug' => 'endnote', 'price' => 20000, 'status' => 'published']);

        $id = $this->postJson('/api/orders', ['tool_id' => $tool->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->assertDatabaseHas('orders', ['id' => $id, 'tool_id' => $tool->id, 'amount' => 20000]);

        // Then pay it like any order.
        $this->getJson("/api/orders/{$id}")->assertOk()->assertJsonPath('data.delivery.type', 'tool');
    }

    public function test_unpublished_tools_cannot_be_ordered_and_products_still_work(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $draft = Tool::create(['name' => 'Draft tool', 'slug' => 'draft-tool', 'price' => 1000, 'status' => 'draft']);

        $this->postJson('/api/orders', ['tool_id' => $draft->id])->assertNotFound();
        $this->postJson('/api/orders', [])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'plan_id']);
    }

    // --- #2 Support chat attachments -----------------------------------------

    public function test_user_can_send_an_image_to_support_and_download_it(): void
    {
        Storage::fake('private');
        Notification::fake();
        Sanctum::actingAs($user = User::factory()->create());

        $message = $this->post('/api/chat', [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('error.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.has_file', true)
            ->json('data');

        $this->get("/api/chat/messages/{$message['id']}/file")->assertOk();

        // Someone else cannot open it.
        Sanctum::actingAs(User::factory()->create());
        $this->get("/api/chat/messages/{$message['id']}/file")->assertNotFound();

        $this->assertSame(1, Conversation::where('user_id', $user->id)->first()->messages()->count());
    }

    public function test_text_messages_still_need_text(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/chat', ['body' => ''])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->postJson('/api/chat', ['body' => 'Hello'])->assertOk()->assertJsonPath('data.has_file', false);
    }

    // --- #6 Add a class to the phone calendar --------------------------------

    public function test_app_gets_a_signed_calendar_link_for_a_scheduled_class(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $host = User::factory()->create(['can_teach' => true]);
        $room = LearningRoom::create([
            'title' => 'Maths live',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
        ]);

        $url = $this->getJson("/api/learning/rooms/{$room->slug}")->assertOk()->json('data.calendar_url');
        $this->assertNotNull($url);

        // The phone opens it with no session or token.
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Maths live', false);

        // A tampered link is refused.
        $this->get(str_replace('u='.$user->id, 'u='.$host->id, $url))->assertForbidden();
    }

    // --- #7 Email verification -----------------------------------------------

    public function test_unverified_user_can_resend_and_verify_by_code_in_the_app(): void
    {
        Notification::fake();
        Sanctum::actingAs($user = User::factory()->unverified()->create());

        $this->postJson('/api/email/verification-notification')->assertOk()->assertJsonPath('verified', false);

        $user->forceFill([
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->postJson('/api/email/verify', ['code' => '000000'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/email/verify', ['code' => '123456'])->assertOk()->assertJsonPath('verified', true);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
