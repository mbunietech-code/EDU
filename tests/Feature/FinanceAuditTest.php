<?php

namespace Tests\Feature;

use App\Models\FinanceExpense;
use App\Models\FinanceMoneyAccount;
use App\Models\User;
use App\Services\Finance\MoneyAccountService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Finance → Audit: checks to fix and the activity trail. */
class FinanceAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        MoneyAccountService::forgetReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null, 'name' => 'Rhoda']);
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    public function test_the_audit_lists_what_needs_fixing_and_who_did_what(): void
    {
        $yas = FinanceMoneyAccount::create(['name' => 'YAS', 'type' => 'mobile_money', 'payment_methods' => []]);

        // Paid out of YAS before anything came in, and typed in twice.
        $this->finance()->post(route('admin.finance.expenses.store'), ['label' => 'VPS', 'amount' => 60000, 'spent_at' => '2026-10-10', 'money_account_id' => $yas->id]);
        $this->finance()->post(route('admin.finance.expenses.store'), ['label' => 'VPS Hostinger', 'amount' => 60000, 'spent_at' => '2026-10-11', 'money_account_id' => $yas->id]);
        $wrong = FinanceExpense::where('label', 'Bundle')->first() ?? FinanceExpense::create(['label' => 'Bundle', 'amount' => 999, 'spent_at' => '2026-10-12', 'money_account_id' => $yas->id]);
        $this->finance()->delete(route('admin.finance.expenses.destroy', $wrong), ['reason' => 'Typed by mistake']);

        $this->finance()->get(route('admin.finance.audit'))->assertOk()
            ->assertSee('Finance audit')
            ->assertSee('Accounts that go below zero')
            ->assertSee('Below zero from 10 Oct 2026')
            ->assertSee('lowest TZS -120,000 on 11 Oct 2026')
            ->assertSee('Possible double entries')
            ->assertSee('VPS and VPS Hostinger · TZS 60,000')
            ->assertSee('Expense added')
            ->assertSee('Expense removed: Bundle')
            ->assertSee('Reason: Typed by mistake')
            ->assertSee('Rhoda');

        $this->finance()->get(route('admin.finance.audit', ['kind' => 'removed']))->assertOk()
            ->assertSee('Expense removed: Bundle')->assertDontSee('Expense added');
    }

    public function test_the_audit_is_behind_the_finance_lock(): void
    {
        $this->actingAs($this->admin)->get(route('admin.finance.audit'))->assertRedirect(route('admin.finance.pin'));
    }
}
