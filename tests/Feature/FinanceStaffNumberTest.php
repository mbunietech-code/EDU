<?php

namespace Tests\Feature;

use App\Models\FinanceStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceStaffNumberTest extends TestCase
{
    use RefreshDatabase;

    private function addStaff(User $admin, array $data = []): void
    {
        $this->actingAs($admin)
            ->withSession(['finance_unlocked' => true])
            ->post(route('admin.finance.staff.store'), array_merge([
                'first_name' => 'Asha',
                'last_name' => 'Juma',
                'status' => 'active',
            ], $data))
            ->assertSessionHasNoErrors();
    }

    public function test_staff_numbers_are_generated_in_mhub_format(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->addStaff($admin);
        $this->addStaff($admin, ['staff_number' => 'CUSTOM-99']);
        $this->addStaff($admin);

        $this->assertSame(
            ['Mhub-001', 'Mhub-002', 'Mhub-003'],
            FinanceStaff::orderBy('id')->pluck('staff_number')->all()
        );
    }

    public function test_staff_numbers_continue_after_the_highest_existing_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        FinanceStaff::create(['staff_number' => 'Mhub-007', 'first_name' => 'Old', 'last_name' => 'Staff', 'status' => 'active', 'basic_salary' => 0]);

        $this->addStaff($admin);

        $this->assertSame('Mhub-008', FinanceStaff::latest('id')->value('staff_number'));
    }

    public function test_bank_account_and_mobile_money_are_saved(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->addStaff($admin, [
            'bank_name' => 'CRDB',
            'bank_account_number' => '0150123456789',
            'mobile_money' => '0712345678',
        ]);

        $staff = FinanceStaff::firstOrFail();
        $this->assertSame('0150123456789', $staff->bank_account_number);
        $this->assertSame('0712345678', $staff->mobile_money);
    }

    public function test_staff_form_lists_tanzanian_banks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->withSession(['finance_unlocked' => true])
            ->get(route('admin.finance.staff.index'))
            ->assertOk()
            ->assertSee('Mhub-001')
            ->assertDontSee('name="staff_number"', false)
            ->assertSee('<option value="CRDB Bank">', false)
            ->assertSee('<option value="NMB Bank">', false)
            ->assertSee('Other (type name)');
    }

    public function test_other_bank_uses_the_typed_name(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->addStaff($admin, ['bank_name' => 'other', 'bank_name_other' => 'Mwalimu Commercial Bank']);

        $this->assertSame('Mwalimu Commercial Bank', FinanceStaff::firstOrFail()->bank_name);
    }
}
