<?php

namespace Tests\Feature;

use App\Models\FinancePayrollPeriod;
use App\Models\FinanceStaff;
use App\Models\FinanceStatutoryReturn;
use App\Models\User;
use App\Services\StatutoryPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceStatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function finance()
    {
        return $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->withSession(['finance_unlocked' => true]);
    }

    private function addStaff(int $count, float $basic): void
    {
        foreach (range(1, $count) as $i) {
            FinanceStaff::create([
                'staff_number' => sprintf('Mhub-%03d', FinanceStaff::count() + 1),
                'first_name' => 'Staff'.$i,
                'last_name' => 'Test',
                'status' => 'active',
                'basic_salary' => $basic,
            ]);
        }
    }

    public function test_paye_follows_tra_monthly_bands(): void
    {
        $service = app(StatutoryPayrollService::class);

        $this->assertSame(0.0, $service->paye(270000));
        $this->assertSame(2400.0, $service->paye(300000));     // 8% of 30,000
        $this->assertSame(36000.0, $service->paye(600000));    // 20,000 + 20% of 80,000
        $this->assertSame(103000.0, $service->paye(900000));   // 68,000 + 25% of 140,000
        $this->assertSame(188000.0, $service->paye(1200000));  // 128,000 + 30% of 200,000
    }

    public function test_payroll_draft_calculates_statutory_amounts_and_returns(): void
    {
        $this->addStaff(1, 1000000);

        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10'])
            ->assertSessionHasNoErrors();

        $item = FinancePayrollPeriod::firstOrFail()->items()->firstOrFail();
        $this->assertSame('100000.00', $item->pension_amount);   // NSSF staff 10%
        $this->assertSame('900000.00', $item->taxable_pay);
        $this->assertSame('103000.00', $item->tax_amount);       // PAYE on 900,000
        $this->assertSame('797000.00', $item->net_pay);
        $this->assertSame('100000.00', $item->nssf_employer);
        $this->assertSame('0.00', $item->sdl_amount);            // fewer than 10 staff
        $this->assertSame('5000.00', $item->wcf_amount);         // 0.5%
        $this->assertSame('1105000.00', $item->employer_cost);
        $this->assertSame('76700.00', $item->leave_provision);
        $this->assertSame('19200.00', $item->severance_provision);

        $returns = FinanceStatutoryReturn::pluck('amount', 'type');
        $this->assertEqualsCanonicalizing(['paye', 'nssf', 'wcf'], $returns->keys()->all());
        $this->assertSame('103000.00', $returns['paye']);
        $this->assertSame('200000.00', $returns['nssf']);

        $this->assertSame('2026-11-07', FinanceStatutoryReturn::where('type', 'paye')->value('due_date')->toDateString());
        $this->assertSame('2026-11-30', FinanceStatutoryReturn::where('type', 'nssf')->value('due_date')->toDateString());
    }

    public function test_sdl_applies_from_ten_staff(): void
    {
        $this->addStaff(10, 500000);

        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10']);

        $payroll = FinancePayrollPeriod::firstOrFail();
        $this->assertSame('175000.00', $payroll->sdl_amount); // 3.5% of 5,000,000
        $this->assertSame('175000.00', FinanceStatutoryReturn::where('type', 'sdl')->value('amount'));
    }

    public function test_statutory_rates_can_be_changed(): void
    {
        $this->addStaff(1, 1000000);
        $rates = config('finance.statutory.rates');
        $rates['wcf'] = 1;

        $this->finance()->put(route('admin.finance.payroll.statutory.update'), $rates)->assertSessionHasNoErrors();
        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10']);

        $this->assertSame('10000.00', FinancePayrollPeriod::firstOrFail()->wcf_amount);
    }

    public function test_paid_returns_survive_a_payroll_regeneration(): void
    {
        $this->addStaff(1, 1000000);
        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10']);

        $paye = FinanceStatutoryReturn::where('type', 'paye')->firstOrFail();
        $this->finance()->post(route('admin.finance.returns.paid', $paye), [
            'paid_at' => now()->toDateString(),
            'reference' => '991234567890',
        ])->assertSessionHasNoErrors();

        FinanceStaff::query()->update(['basic_salary' => 2000000]);
        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10']);

        $paye->refresh();
        $this->assertSame('paid', $paye->status);
        $this->assertSame('103000.00', $paye->amount);
        $this->assertSame('991234567890', $paye->reference);
        $this->assertSame('400000.00', FinanceStatutoryReturn::where('type', 'nssf')->value('amount'));
    }

    public function test_returns_and_payroll_pages_render(): void
    {
        $this->addStaff(1, 1000000);
        $this->finance()->post(route('admin.finance.payroll.store'), ['period_month' => '2026-10']);

        $this->finance()->get(route('admin.finance.returns.index'))
            ->assertOk()
            ->assertSee('PAYE')
            ->assertSee('Pay to TRA')
            ->assertSee('TZS 103,000')
            ->assertSee('Skills Development Levy');

        $this->finance()->get(route('admin.finance.payroll.index'))
            ->assertOk()
            ->assertSee('Statutory settings (Tanzania)')
            ->assertSee('Employer cost')
            ->assertSee('TZS 1,105,000');
    }
}
