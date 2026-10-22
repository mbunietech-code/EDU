<?php

namespace Tests\Feature;

use App\Models\FinanceExpense;
use App\Models\FinanceMoneyAccount;
use App\Models\FinancePayrollPeriod;
use App\Models\FinanceStaff;
use App\Models\FinanceStatutoryReturn;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePayrollPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FinanceExpense::forgetTypesReady();
        \App\Services\Finance\MoneyAccountService::forgetReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    /** Two staff on 1,000,000 basic, October 2026 draft prepared. */
    private function draft(): FinancePayrollPeriod
    {
        foreach (['Asha', 'Baraka'] as $i => $name) {
            FinanceStaff::create([
                'staff_number' => sprintf('Mhub-%03d', $i + 1),
                'first_name' => $name,
                'last_name' => 'Test',
                'status' => 'active',
                'basic_salary' => 1000000,
            ]);
        }
        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10'])->assertSessionHas('success');

        return FinancePayrollPeriod::firstOrFail();
    }

    public function test_one_person_can_get_different_allowances_on_the_draft(): void
    {
        $payroll = $this->draft();
        $asha = $payroll->items()->where('staff_name', 'like', 'Asha%')->first();
        $baraka = $payroll->items()->where('staff_name', 'like', 'Baraka%')->first();
        $netBefore = (float) $payroll->net_pay;
        $payeBefore = (float) $payroll->tax_amount;

        $this->finance()->put(route('admin.finance.payroll.items.update', $asha), ['allowances' => 100000, 'bonus_pay' => 50000])
            ->assertSessionHas('success');

        $asha->refresh();
        $this->assertEquals(1150000, (float) $asha->gross_pay);
        $this->assertEquals(0, (float) $baraka->fresh()->allowances, 'Only Asha changed.');

        $payroll->refresh();
        $this->assertEquals(150000, (float) $payroll->total_allowances + (float) $payroll->bonus_pay - (float) $payroll->overtime_pay);
        $this->assertEquals(2150000, (float) $payroll->gross_pay);
        $this->assertGreaterThan($netBefore, (float) $payroll->net_pay);
        $this->assertGreaterThan($payeBefore, (float) $payroll->tax_amount, 'PAYE follows the higher gross.');
        $this->assertEquals((float) $payroll->tax_amount, (float) $payroll->statutoryReturns()->where('type', 'paye')->value('amount'), 'Returns follow the new totals.');
    }

    public function test_draft_approved_paid_records_net_pay_once_from_the_chosen_account(): void
    {
        $payroll = $this->draft();
        $crdb = FinanceMoneyAccount::create(['name' => 'CRDB', 'type' => 'bank']);

        // Paying a draft is refused.
        $this->finance()->post(route('admin.finance.payroll.paid', $payroll), ['paid_at' => '2026-10-20'])->assertSessionHas('error');

        $this->finance()->post(route('admin.finance.payroll.approve', $payroll))->assertSessionHas('success');
        $this->assertSame('approved', $payroll->fresh()->status);

        // Lines are locked once approved; back to draft unlocks them.
        $line = $payroll->items()->first();
        $this->finance()->put(route('admin.finance.payroll.items.update', $line), ['allowances' => 1])->assertSessionHas('error');
        $this->finance()->post(route('admin.finance.payroll.reopen', $payroll))->assertSessionHas('success');
        $this->assertSame('draft', $payroll->fresh()->status);
        $this->finance()->post(route('admin.finance.payroll.approve', $payroll))->assertSessionHas('success');

        $this->finance()->get(route('admin.finance.payroll.index'))->assertOk()->assertSee('Mark paid')->assertSee('Paid from');
        $this->finance()->post(route('admin.finance.payroll.paid', $payroll), ['paid_at' => '2026-10-20', 'money_account_id' => $crdb->id])
            ->assertSessionHas('success');

        $payroll->refresh();
        $this->assertSame('paid', $payroll->status);
        $expense = FinanceExpense::where('source_type', 'payroll')->where('source_id', $payroll->id)->sole();
        $this->assertEquals((float) $payroll->net_pay, (float) $expense->amount);
        $this->assertSame('salaries', $expense->expense_type);
        $this->assertSame('Salaries – October 2026', $expense->label);
        $this->assertSame($crdb->id, $expense->money_account_id);
        $this->assertSame('2026-10-20', $expense->spent_at->toDateString());

        // A second "paid" is refused and never doubles the expense.
        $this->finance()->post(route('admin.finance.payroll.paid', $payroll), ['paid_at' => '2026-10-21'])->assertSessionHas('error');
        $this->assertSame(1, FinanceExpense::count());

        $this->finance()->get(route('admin.finance.payroll.index'))->assertOk()->assertSee('Salaries – October 2026');
        $this->finance()->get(route('admin.finance.money.show', $crdb))->assertOk()->assertSee('Salaries – October 2026');
    }

    public function test_paying_a_return_records_it_and_undo_removes_it(): void
    {
        $this->draft();
        $crdb = FinanceMoneyAccount::create(['name' => 'CRDB', 'type' => 'bank']);
        $paye = FinanceStatutoryReturn::where('type', 'paye')->firstOrFail();

        $this->finance()->get(route('admin.finance.returns.index'))->assertOk()->assertSee('Paid from');
        $this->finance()->post(route('admin.finance.returns.paid', $paye), [
            'paid_at' => '2026-10-19', 'reference' => 'CN-123', 'money_account_id' => $crdb->id,
        ])->assertSessionHas('success');

        $this->assertTrue($paye->fresh()->isPaid());
        $expense = FinanceExpense::where('source_type', 'statutory_return')->where('source_id', $paye->id)->sole();
        $this->assertEquals((float) $paye->amount, (float) $expense->amount);
        $this->assertSame('payroll_taxes', $expense->expense_type);
        $this->assertSame($crdb->id, $expense->money_account_id);
        $this->assertStringContainsString('PAYE', $expense->label);

        $this->finance()->post(route('admin.finance.returns.pending', $paye))->assertRedirect();
        $this->assertFalse($paye->fresh()->isPaid());
        $this->assertSame(0, FinanceExpense::count(), 'Undo removes the automatic expense.');

        $this->finance()->post(route('admin.finance.returns.paid', $paye), ['paid_at' => '2026-10-21'])->assertSessionHas('success');
        $this->assertSame(1, FinanceExpense::count());
    }

    public function test_app_api_approves_and_pays_payroll(): void
    {
        $payroll = $this->draft();
        Setting::set('finance_pin', '123456');
        $token = $this->admin->createToken('app')->plainTextToken;
        $api = fn (string $m, string $uri, array $d = []) => $this->withHeader('Authorization', 'Bearer '.$token)->json($m, '/api/admin/finance'.$uri, $d);
        $api('POST', '/unlock', ['pin' => '123456'])->assertOk();

        $line = $payroll->items()->first();
        $api('PUT', "/payroll/items/{$line->id}", ['allowances' => 20000])->assertOk()->assertJsonPath('data.id', $line->id);
        $api('POST', "/payroll/{$payroll->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $api('POST', "/payroll/{$payroll->id}/paid", ['paid_at' => '2026-10-20'])->assertOk()->assertJsonPath('data.status', 'paid');
        $api('POST', "/payroll/{$payroll->id}/approve")->assertStatus(422);

        $this->assertSame(1, FinanceExpense::where('source_type', 'payroll')->count());
    }
}
