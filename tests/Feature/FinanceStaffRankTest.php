<?php

namespace Tests\Feature;

use App\Models\FinancePosition;
use App\Models\FinanceStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceStaffRankTest extends TestCase
{
    use RefreshDatabase;

    private function finance()
    {
        return $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->withSession(['finance_unlocked' => true]);
    }

    private function staff(string $first, ?FinancePosition $position, string $number): FinanceStaff
    {
        return FinanceStaff::create([
            'staff_number' => $number,
            'first_name' => $first,
            'last_name' => 'Test',
            'status' => 'active',
            'basic_salary' => 0,
            'finance_position_id' => $position?->id,
        ]);
    }

    public function test_seniority_is_guessed_from_the_title(): void
    {
        $this->assertSame(20, FinancePosition::guessSeniority('Chief Executive Officer (CEO)'));
        $this->assertSame(30, FinancePosition::guessSeniority('Managing Director'));
        $this->assertSame(40, FinancePosition::guessSeniority('Deputy CEO'));
        $this->assertSame(70, FinancePosition::guessSeniority('Sales Manager'));
        $this->assertSame(100, FinancePosition::guessSeniority('Executive Assistant to CEO'));
        $this->assertSame(90, FinancePosition::guessSeniority('Accountant'));
    }

    public function test_staff_dashboard_lists_most_senior_first(): void
    {
        $md = FinancePosition::create(['name' => 'Managing Director', 'seniority' => 30]);
        $ceo = FinancePosition::create(['name' => 'Chief Executive Officer (CEO)', 'seniority' => 20]);
        $officer = FinancePosition::create(['name' => 'IT Officer', 'seniority' => 90]);

        $this->staff('Nopos', null, 'Mhub-001');
        $this->staff('Officer', $officer, 'Mhub-002');
        $this->staff('Rhoda', $md, 'Mhub-003');
        $this->staff('Mussa', $ceo, 'Mhub-004');

        $this->finance()->get(route('admin.finance.staff.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Staff by rank', 'Mussa', 'Rhoda', 'Officer', 'Nopos']);
    }

    public function test_position_gets_auto_seniority_and_can_be_reranked(): void
    {
        $this->finance()->post(route('admin.finance.staff.settings.store'), [
            'type' => 'position',
            'name' => 'Managing Director',
        ])->assertSessionHasNoErrors();

        $this->assertSame(30, FinancePosition::where('name', 'Managing Director')->value('seniority'));

        $this->finance()->post(route('admin.finance.staff.settings.store'), [
            'type' => 'position',
            'name' => 'Managing Director',
            'seniority' => 15,
        ])->assertSessionHasNoErrors();

        $this->assertSame(15, FinancePosition::where('name', 'Managing Director')->value('seniority'));
        $this->assertSame(1, FinancePosition::count());
    }
}
