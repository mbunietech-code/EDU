<?php

namespace Tests\Feature;

use App\Models\FinanceCapitalEntry;
use App\Models\FinanceLoanRepayment;
use App\Models\User;
use App\Services\FinanceOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceLoanRepaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    private function loan(float $amount = 250000, bool $isLoan = true): FinanceCapitalEntry
    {
        return FinanceCapitalEntry::create([
            'label' => 'Grammarly',
            'amount' => $amount,
            'source' => 'Rhoda Kihwele',
            'is_loan' => $isLoan,
        ]);
    }

    private function repay(FinanceCapitalEntry $loan, float $amount)
    {
        return $this->finance()->post(route('admin.finance.capital.repayments.store', $loan), [
            'amount' => $amount,
            'paid_at' => now()->toDateString(),
            'method' => 'M-Pesa',
        ]);
    }

    public function test_loan_can_be_repaid_in_parts_until_cleared(): void
    {
        $loan = $this->loan();

        $this->repay($loan, 100000)->assertSessionHasNoErrors();
        $this->assertSame(150000.0, $loan->fresh()->outstandingAmount());
        $this->assertFalse($loan->fresh()->isFullyRepaid());

        $this->repay($loan, 150000)->assertSessionHasNoErrors();
        $this->assertSame(0.0, $loan->fresh()->outstandingAmount());
        $this->assertTrue($loan->fresh()->isFullyRepaid());
    }

    public function test_repayment_cannot_exceed_outstanding_balance(): void
    {
        $loan = $this->loan(50000);

        $this->repay($loan, 60000)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('finance_loan_repayments', 0);
    }

    public function test_non_loan_capital_cannot_be_repaid(): void
    {
        $this->repay($this->loan(50000, false), 1000)->assertNotFound();
    }

    public function test_repayment_page_and_capital_list_show_balances(): void
    {
        $loan = $this->loan();
        $this->repay($loan, 100000);

        $this->finance()->get(route('admin.finance.capital.repayments.index', $loan))
            ->assertOk()
            ->assertSee('TZS 150,000')
            ->assertSee('M-Pesa');

        $this->finance()->get(route('admin.finance.capital.index'))
            ->assertOk()
            ->assertSee('Owes TZS 150,000')
            ->assertSee('Repay');
    }

    public function test_repayments_reduce_the_overview_balance(): void
    {
        $loan = $this->loan();
        $this->repay($loan, 100000);

        $overview = app(FinanceOverviewService::class);
        $totals = $overview->totals($overview->rows());

        $this->assertSame(100000.0, $totals['repaid']);
        $this->assertSame(150000.0, $totals['balance']);
    }

    public function test_repayment_can_be_removed_with_a_reason(): void
    {
        $loan = $this->loan();
        $this->repay($loan, 100000);
        $repayment = FinanceLoanRepayment::firstOrFail();

        $this->finance()
            ->delete(route('admin.finance.capital.repayments.destroy', [$loan, $repayment]), ['reason' => 'Entered twice'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('finance_loan_repayments', 0);
        $this->assertSame(250000.0, $loan->fresh()->outstandingAmount());
    }

    public function test_loan_amount_cannot_drop_below_repaid_total(): void
    {
        $loan = $this->loan();
        $this->repay($loan, 100000);

        $this->finance()->put(route('admin.finance.capital.update', $loan), [
            'label' => 'Grammarly',
            'amount' => 50000,
            'source' => 'Rhoda Kihwele',
            'is_loan' => 1,
        ])->assertSessionHasErrors('amount');

        $this->assertSame('250000.00', $loan->fresh()->amount);
    }
}
