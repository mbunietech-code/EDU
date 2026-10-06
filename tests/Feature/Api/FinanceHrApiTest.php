<?php

namespace Tests\Feature\Api;

use App\Models\FinanceCapitalEntry;
use App\Models\FinancePosition;
use App\Models\FinanceStaff;
use App\Models\FinanceStatutoryReturn;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinanceHrApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('finance_pin', '123456');
        $admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $this->token = $admin->createToken('app')->plainTextToken;
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->json($method, '/api/admin/finance'.$uri, $data);
    }

    private function unlock(): void
    {
        $this->api('POST', '/unlock', ['pin' => '123456'])->assertOk();
    }

    public function test_finance_hr_needs_the_pin(): void
    {
        $this->api('GET', '/staff')->assertStatus(423);
        $this->unlock();
        $this->api('GET', '/staff')->assertOk();
    }

    public function test_staff_can_be_added_and_listed_by_rank(): void
    {
        $this->unlock();
        $md = FinancePosition::create(['name' => 'Managing Director', 'seniority' => 30]);
        $ceo = FinancePosition::create(['name' => 'Chief Executive Officer', 'seniority' => 20]);

        $this->api('POST', '/staff', [
            'first_name' => 'Rhoda', 'last_name' => 'Kihwele', 'status' => 'active', 'basic_salary' => 900000,
            'finance_position_id' => $md->id, 'bank_name' => 'other', 'bank_name_other' => 'Mwalimu Bank',
        ])->assertCreated()->assertJsonPath('data.staff_number', 'Mhub-001');

        $this->api('POST', '/staff', [
            'first_name' => 'Mussa', 'last_name' => 'Awadhi', 'status' => 'active', 'basic_salary' => 1000000,
            'finance_position_id' => $ceo->id,
        ])->assertCreated()->assertJsonPath('data.staff_number', 'Mhub-002');

        $this->api('GET', '/staff')
            ->assertOk()
            ->assertJsonPath('data.staff.0.name', 'Mussa Awadhi')
            ->assertJsonPath('data.staff.1.bank_name', 'Mwalimu Bank')
            ->assertJsonPath('data.next_staff_number', 'Mhub-003')
            ->assertJsonPath('data.metrics.active', 2);
    }

    public function test_payroll_and_returns_from_the_app(): void
    {
        $this->unlock();
        FinanceStaff::create(['staff_number' => 'Mhub-001', 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active', 'basic_salary' => 1000000]);

        $this->api('POST', '/payroll', ['period_month' => '2026-10'])->assertCreated();

        $this->api('GET', '/payroll')
            ->assertOk()
            ->assertJsonPath('data.latest.paye', 103000)
            ->assertJsonPath('data.latest.net_pay', 797000)
            ->assertJsonPath('data.latest.items.0.staff_name', 'A B');

        $paye = FinanceStatutoryReturn::where('type', 'paye')->firstOrFail();
        $this->api('POST', "/returns/{$paye->id}/paid", ['paid_at' => now()->toDateString(), 'reference' => '9912'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // Paid returns are listed last.
        $returns = $this->api('GET', '/returns')->assertOk()->json('data.returns');
        $this->assertSame(['NSSF', 'WCF', 'PAYE'], array_column($returns, 'label'));
        $this->assertSame('paid', end($returns)['status']);

        $this->api('POST', "/returns/{$paye->id}/pending")->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_payroll_without_staff_returns_a_message(): void
    {
        $this->unlock();

        $this->api('POST', '/payroll', ['period_month' => '2026-10'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Add active staff before preparing payroll.');
    }

    public function test_statutory_rates_can_be_changed(): void
    {
        $this->unlock();
        $rates = config('finance.statutory.rates');
        $rates['wcf'] = 0.6;

        $this->api('PUT', '/payroll/rates', $rates)->assertOk();
        $this->api('GET', '/payroll')->assertJsonPath('data.rates.wcf', 0.6);
    }

    public function test_loans_can_be_repaid_from_the_app(): void
    {
        $this->unlock();
        $loan = FinanceCapitalEntry::create(['label' => 'Grammarly', 'amount' => 250000, 'source' => 'Rhoda', 'is_loan' => true]);

        $this->api('POST', "/loans/{$loan->id}/repayments", ['amount' => 300000, 'paid_at' => now()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->api('POST', "/loans/{$loan->id}/repayments", ['amount' => 100000, 'paid_at' => now()->toDateString(), 'method' => 'M-Pesa'])
            ->assertCreated()
            ->assertJsonPath('data.outstanding', 150000);

        $this->api('GET', '/loans')->assertOk()->assertJsonPath('data.totals.outstanding', 150000);

        $loanDetail = $this->api('GET', "/loans/{$loan->id}")->assertOk()->assertJsonPath('data.repayments.0.method', 'M-Pesa');
        $repaymentId = $loanDetail->json('data.repayments.0.id');

        $this->api('DELETE', "/loans/{$loan->id}/repayments/{$repaymentId}", ['reason' => 'Mistake'])->assertOk();
        $this->api('GET', "/loans/{$loan->id}")->assertJsonPath('data.outstanding', 250000);
    }

    public function test_gateway_settings_for_the_admin_app(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->withHeaders($headers)->putJson('/api/admin/payment-gateways/clickpesa', [
            'enabled' => true, 'client_id' => 'cp-id', 'api_key' => 'cp-secret',
        ])->assertOk()->assertJsonPath('ready', true);

        $list = $this->withHeaders($headers)->getJson('/api/admin/payment-gateways')->assertOk();
        $clickpesa = collect($list->json('data'))->firstWhere('key', 'clickpesa');
        $this->assertTrue($clickpesa['ready']);
        $apiKey = collect($clickpesa['fields'])->firstWhere('key', 'api_key');
        $this->assertNull($apiKey['value']);
        $this->assertTrue($apiKey['is_set']);
        $this->assertStringContainsString('/api/payments/callback/clickpesa/', $clickpesa['callback_url']);

        Http::fake(['api.clickpesa.com/*' => Http::response(['token' => 'Bearer ok'])]);
        $this->withHeaders($headers)->postJson('/api/admin/payment-gateways/clickpesa/test')
            ->assertOk()->assertJsonPath('ok', true);
    }
}
