<?php

namespace Tests\Feature;

use App\Models\FinanceExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceExpenseReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_expense_can_be_created_with_a_receipt(): void
    {
        Storage::fake('private');

        $response = $this->actingAs($this->admin())
            ->withSession(['finance_unlocked' => true])
            ->post(route('admin.finance.expenses.store'), [
                'label' => 'Grammarly',
                'amount' => 12000,
                'spent_at' => '2026-08-30',
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
            ]);

        $response->assertRedirect();

        $expense = FinanceExpense::firstOrFail();
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('private')->assertExists($expense->receipt_path);
    }

    public function test_receipt_rejects_disallowed_file_types(): void
    {
        Storage::fake('private');

        $response = $this->actingAs($this->admin())
            ->withSession(['finance_unlocked' => true])
            ->post(route('admin.finance.expenses.store'), [
                'label' => 'Bad file',
                'amount' => 5000,
                'spent_at' => '2026-08-30',
                'receipt' => UploadedFile::fake()->create('malware.exe', 20, 'application/octet-stream'),
            ]);

        $response->assertSessionHasErrors('receipt');
        $this->assertDatabaseCount('finance_expenses', 0);
    }

    public function test_admin_can_download_a_receipt(): void
    {
        Storage::fake('private');
        $path = UploadedFile::fake()->image('r.png')->store('finance-receipts', 'private');
        $expense = FinanceExpense::create([
            'label' => 'Hosting',
            'amount' => 30000,
            'spent_at' => '2026-08-30',
            'receipt_path' => $path,
        ]);

        $this->actingAs($this->admin())
            ->withSession(['finance_unlocked' => true])
            ->get(route('admin.finance.expenses.receipt', $expense))
            ->assertOk();
    }

    public function test_receipt_can_be_replaced_and_removed_on_update(): void
    {
        Storage::fake('private');
        $oldPath = UploadedFile::fake()->image('old.png')->store('finance-receipts', 'private');
        $expense = FinanceExpense::create([
            'label' => 'Hosting',
            'amount' => 30000,
            'spent_at' => '2026-08-30',
            'receipt_path' => $oldPath,
        ]);

        // Replace
        $this->actingAs($this->admin())
            ->withSession(['finance_unlocked' => true])
            ->put(route('admin.finance.expenses.update', $expense), [
                'label' => 'Hosting',
                'amount' => 30000,
                'spent_at' => '2026-08-30',
                'receipt' => UploadedFile::fake()->image('new.png'),
            ])->assertRedirect();

        $expense->refresh();
        Storage::disk('private')->assertMissing($oldPath);
        Storage::disk('private')->assertExists($expense->receipt_path);
        $replacedPath = $expense->receipt_path;

        // Remove
        $this->actingAs($this->admin())
            ->withSession(['finance_unlocked' => true])
            ->put(route('admin.finance.expenses.update', $expense), [
                'label' => 'Hosting',
                'amount' => 30000,
                'spent_at' => '2026-08-30',
                'remove_receipt' => '1',
            ])->assertRedirect();

        $expense->refresh();
        $this->assertNull($expense->receipt_path);
        Storage::disk('private')->assertMissing($replacedPath);
    }
}
