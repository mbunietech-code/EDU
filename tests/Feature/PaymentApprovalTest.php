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

    public function test_approval_fails_when_no_account_is_available(): void
    {
        $context = $this->createOrderContext(['accounts' => 0]);
        $payment = $this->createPendingPayment($context['order'], $context['user']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.payments.approve', $payment));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseHas('orders', ['id' => $context['order']->id, 'status' => 'pending']);
        $this->assertDatabaseCount('subscriptions', 0);
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
