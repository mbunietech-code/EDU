<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\Admin\ErrorOccurred;
use App\Notifications\Admin\NewContactMessage;
use App\Notifications\Admin\NewOrder;
use App\Notifications\User\OrderCreated;
use App\Services\NotificationService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Staff notifications (new orders, payments, errors…) reach only admins with
 * the permission for that area, and customers never see them.
 */
class AdminNotificationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function limitedAdmin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(['is_admin' => false] + $attributes);
    }

    private function stored(User $user, string $type, string $message): void
    {
        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => $message],
        ]);
    }

    public function test_customer_does_not_see_old_staff_notifications_but_sees_their_own(): void
    {
        $customer = $this->customer();
        $this->stored($customer, NewOrder::class, 'New order from someone else');
        $this->stored($customer, ErrorOccurred::class, 'Server error on checkout');
        $this->stored($customer, OrderCreated::class, 'Your order was received');

        $this->assertSame(1, $customer->notifications()->count());
        $this->assertSame(1, $customer->unreadNotifications()->count());

        $this->actingAs($customer)->get(route('user.notifications.index'))
            ->assertOk()
            ->assertSee('Your order was received')
            ->assertDontSee('New order from someone else')
            ->assertDontSee('Server error on checkout');

        \Laravel\Sanctum\Sanctum::actingAs($customer);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread', 1)
            ->assertJsonPath('data.0.message', 'Your order was received');
    }

    public function test_an_admin_turned_back_into_a_customer_stops_seeing_staff_notifications(): void
    {
        $former = $this->superAdmin();
        $this->stored($former, NewOrder::class, 'New order');
        $this->assertSame(1, $former->notifications()->count());

        $former->update(['role' => Permissions::ROLE_USER, 'is_admin' => false]);

        $this->assertSame(0, $former->fresh()->notifications()->count());
    }

    public function test_limited_admin_sees_only_the_areas_they_may_open(): void
    {
        $admin = $this->limitedAdmin(['orders.view']);
        $this->stored($admin, NewOrder::class, 'New order');
        $this->stored($admin, ErrorOccurred::class, 'Server error');

        $this->assertSame(['New order'], $admin->notifications()->get()->pluck('data.message')->all());
    }

    public function test_super_admin_still_sees_every_staff_notification(): void
    {
        $admin = $this->superAdmin();
        $this->stored($admin, NewOrder::class, 'New order');
        $this->stored($admin, ErrorOccurred::class, 'Server error');

        $this->assertSame(2, $admin->notifications()->count());
    }

    public function test_staff_notifications_go_only_to_admins_with_that_permission(): void
    {
        Notification::fake();

        $super = $this->superAdmin();
        $contactStaff = $this->limitedAdmin(['contact_messages.view']);
        $ordersStaff = $this->limitedAdmin(['orders.view']);
        $customer = $this->customer();

        $message = ContactMessage::query()->create(['name' => 'Asha', 'email' => 'asha@example.com', 'subject' => 'Hello', 'message' => 'Hi']);
        app(NotificationService::class)->notifyAdminNewContactMessage($message);

        Notification::assertSentTo([$super, $contactStaff], NewContactMessage::class);
        Notification::assertNotSentTo($ordersStaff, NewContactMessage::class);
        Notification::assertNotSentTo($customer, NewContactMessage::class);
    }

    public function test_storing_notifications_still_works_with_the_filter(): void
    {
        $admin = $this->superAdmin();
        $message = ContactMessage::query()->create(['name' => 'Asha', 'email' => 'asha@example.com', 'subject' => 'Hello', 'message' => 'Hi']);

        app(NotificationService::class)->notifyAdminNewContactMessage($message);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(NewContactMessage::class, $admin->notifications()->first()->type);
    }
}
