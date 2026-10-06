<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Notifications\User\ExpiryWarning;
use App\Notifications\User\SubscriptionExpired;
use App\Services\PaymentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionExpiryEmailTest extends TestCase
{
    use CreatesOrderContext;
    use RefreshDatabase;

    private function activeSubscription(): array
    {
        $context = $this->createOrderContext();
        app(PaymentApprovalService::class)->approve($this->createPendingPayment($context['order'], $context['user']));

        $subscription = Subscription::firstOrFail();
        $subscription->update(['expiry_date' => now()->addDays(2)->toDateString()]);

        return [$context['user'], $subscription];
    }

    public function test_customer_is_emailed_before_and_when_the_subscription_ends(): void
    {
        Notification::fake();
        [$user, $subscription] = $this->activeSubscription();

        // Two days before: warning email.
        $this->artisan('subscriptions:process-expiry')->assertSuccessful();
        $this->assertSame('expiring_soon', $subscription->fresh()->status);
        Notification::assertSentTo($user, ExpiryWarning::class);
        Notification::assertNotSentTo($user, SubscriptionExpired::class);

        // After the expiry date: expired, account released, expired email.
        $this->travel(3)->days();
        $this->artisan('subscriptions:process-expiry')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertSame('available', $subscription->account->fresh()->status);
        Notification::assertSentTo($user, SubscriptionExpired::class);
        Notification::assertSentToTimes($user, ExpiryWarning::class, 1);
    }

    public function test_expired_email_is_sent_only_once(): void
    {
        Notification::fake();
        [$user] = $this->activeSubscription();

        $this->travel(3)->days();
        $this->artisan('subscriptions:process-expiry');
        $this->artisan('subscriptions:process-expiry');

        Notification::assertSentToTimes($user, SubscriptionExpired::class, 1);
    }
}
