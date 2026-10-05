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
        $this->addStaff($admin, ['staff_number' => 'Mhub-007']);
        $this->addStaff($admin);

        $this->assertSame(
            ['Mhub-001', 'Mhub-007', 'Mhub-008'],
            FinanceStaff::orderBy('id')->pluck('staff_number')->all()
        );
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
}
