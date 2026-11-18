<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApprovalTest extends TestCase
{
    use RefreshDatabase, CreatesOrderContext;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_approve_a_pending_payment(): void
    {
        $context = $this->createOrderContext(['accounts' => 1]);
        $payment = $this->createPendingPayment($context['order'], $context['user']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.payments.approve', $payment));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'approved']);
        $this->assertDatabaseHas('orders', ['id' => $context['order']->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('subscriptions', [
            'order_id' => $context['order']->id,
            'status' => 'active',
        ]);

        $subscription = Subscription::where('order_id', $context['order']->id)->first();
        $account = Account::find($subscription->account_id);
        $this->assertEquals('assigned', $account->status);
    }

    public function test_approval_with_empty_stock_creates_an_account_waiting_for_login(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $context = $this->createOrderContext(['accounts' => 0]);
        $payment = $this->createPendingPayment($context['order'], $context['user']);

        $this->actingAs($this->admin())
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Paid orders are never stuck because the stock is empty.
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'approved']);
        $this->assertDatabaseHas('orders', ['id' => $context['order']->id, 'status' => 'confirmed']);

        $subscription = Subscription::where('order_id', $context['order']->id)->firstOrFail();
        $account = Account::findOrFail($subscription->account_id);
        $this->assertSame('assigned', $account->status);
        $this->assertNull($account->credentials);
        $this->assertTrue($account->isAwaitingSetup());

        // The customer is told the login is on its way, not shown an error.
        $this->actingAs($context['user'])->get(route('user.subscriptions.show', $subscription))
            ->assertOk()
            ->assertSee('Payment received');

        // The admin adds the login: the account is ready and the customer is told.
        $this->actingAs(User::factory()->create(['is_admin' => true, 'role' => \App\Support\Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]))
            ->put(route('admin.accounts.update', $account), [
                'product_id' => $account->product_id,
                'name' => 'ChatGPT team seat 4',
                'credentials' => "email: seat4@example.com\npassword: s3cret",
                'status' => 'assigned',
            ])->assertRedirect();

        $account->refresh();
        $this->assertFalse($account->isAwaitingSetup());
        \Illuminate\Support\Facades\Notification::assertSentTo($context['user'], \App\Notifications\User\SubscriptionActivated::class);

        $this->actingAs($context['user'])->get(route('user.subscriptions.show', $subscription))
            ->assertSee('seat4@example.com');
    }

    public function test_non_admin_cannot_approve_payment(): void
    {
        $context = $this->createOrderContext(['accounts' => 1]);
        $payment = $this->createPendingPayment($context['order'], $context['user']);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('admin.payments.approve', $payment));

        $response->assertForbidden();
    }

    public function test_admin_can_reject_a_pending_payment(): void
    {
        $context = $this->createOrderContext(['accounts' => 1]);
        $payment = $this->createPendingPayment($context['order'], $context['user']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.payments.reject', $payment), ['reason' => 'blurry receipt']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'rejected']);
        $this->assertDatabaseCount('subscriptions', 0);
    }
}
