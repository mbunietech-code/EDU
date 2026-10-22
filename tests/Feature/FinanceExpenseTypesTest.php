<?php

namespace Tests\Feature;

use App\Models\FinanceExpense;
use App\Models\Product;
use App\Models\User;
use App\Services\FinanceOverviewService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceExpenseTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        FinanceExpense::forgetTypesReady();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    protected function tearDown(): void
    {
        FinanceExpense::forgetTypesReady();
        parent::tearDown();
    }

    private function finance()
    {
        return $this->actingAs($this->admin)->withSession(['finance_unlocked' => true]);
    }

    public function test_a_type_sets_the_category_and_shows_on_the_list(): void
    {
        $this->finance()->get(route('admin.finance.expenses.index'))->assertOk()
            ->assertSee('Choose type')->assertSee('Electricity (LUKU)')->assertSee('data-category="Operating Expenses (OPEX)"', false);

        $this->finance()->post(route('admin.finance.expenses.store'), [
            'expense_type' => 'electricity', 'label' => 'LUKU October', 'amount' => 30000, 'spent_at' => '2026-10-10',
        ])->assertSessionHas('success');

        $e = FinanceExpense::first();
        $this->assertSame('electricity', $e->expense_type);
        $this->assertSame('Operating Expenses (OPEX)', $e->category, 'No category given: taken from the type.');

        $this->finance()->get(route('admin.finance.expenses.index', ['type' => 'electricity']))->assertOk()
            ->assertSee('LUKU October');
        $this->finance()->get(route('admin.finance.expenses.index', ['type' => 'internet']))->assertOk()
            ->assertDontSee('LUKU October');
    }

    public function test_an_explicit_category_is_kept_and_unknown_types_are_refused(): void
    {
        $this->finance()->post(route('admin.finance.expenses.store'), [
            'expense_type' => 'equipment', 'label' => 'Laptop', 'category' => 'Miscellaneous', 'amount' => 900000, 'spent_at' => '2026-10-10',
        ])->assertSessionHas('success');
        $this->assertSame('Miscellaneous', FinanceExpense::first()->category);

        $this->finance()->post(route('admin.finance.expenses.store'), [
            'expense_type' => 'yacht', 'label' => 'Boat', 'amount' => 1, 'spent_at' => '2026-10-10',
        ])->assertSessionHasErrors('expense_type');
    }

    public function test_older_expenses_without_a_type_are_found_and_can_be_typed(): void
    {
        $old = FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'category' => 'Miscellaneous', 'spent_at' => '2026-09-01']);
        $typed = FinanceExpense::create(['label' => 'Wifi', 'amount' => 70000, 'spent_at' => '2026-09-02', 'expense_type' => 'internet']);

        $this->finance()->get(route('admin.finance.expenses.index', ['type' => 'none']))->assertOk()
            ->assertSee('Bundle')->assertDontSee('Wifi');

        $this->finance()->put(route('admin.finance.expenses.update', $old), [
            'expense_type' => 'internet', 'label' => 'Bundle', 'amount' => 10000, 'category' => 'Miscellaneous', 'spent_at' => '2026-09-01',
        ])->assertRedirect();
        $this->assertSame('internet', $old->fresh()->expense_type);
        $this->assertEquals(10000, $old->fresh()->amount, 'Only the type changed.');
    }

    public function test_overview_puts_general_costs_of_one_type_on_one_line(): void
    {
        $product = Product::factory()->create(['name' => 'ChatGPT', 'status' => 'published']);
        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-09-01', 'expense_type' => 'internet']);
        FinanceExpense::create(['label' => 'Wifi', 'amount' => 70000, 'spent_at' => '2026-09-02', 'expense_type' => 'internet']);
        FinanceExpense::create(['label' => 'ChatGPT', 'amount' => 90000, 'spent_at' => '2026-10-19', 'expense_type' => 'product_accounts', 'product_id' => $product->id]);
        FinanceExpense::create(['label' => 'Forex', 'amount' => 15500, 'spent_at' => '2026-10-20']);

        $rows = collect(app(FinanceOverviewService::class)->rows())->keyBy('label');

        $this->assertSame(80000.0, (float) $rows['Internet / bundles']['expenses']);
        $this->assertSame(90000.0, (float) $rows['ChatGPT']['expenses'], 'A product cost stays on its product.');
        $this->assertSame(15500.0, (float) $rows['Forex']['expenses'], 'Untyped expenses keep their own line.');
    }

    public function test_pages_work_before_the_database_change_is_applied(): void
    {
        FinanceExpense::create(['label' => 'Bundle', 'amount' => 10000, 'spent_at' => '2026-09-01']);
        Schema::table('finance_expenses', function ($t) {
            $t->dropIndex('finance_expenses_source_index');
            $t->dropIndex(['expense_type']);
            $t->dropColumn(['expense_type', 'source_type', 'source_id']);
        });
        FinanceExpense::forgetTypesReady();

        $this->finance()->get(route('admin.finance.expenses.index', ['type' => 'none']))->assertOk()
            ->assertDontSee('Choose type')->assertSee('Bundle');
        $this->finance()->get(route('admin.finance.dashboard'))->assertOk();
        $this->finance()->post(route('admin.finance.expenses.store'), [
            'expense_type' => 'electricity', 'label' => 'Umeme', 'amount' => 30000, 'spent_at' => '2026-10-10',
        ])->assertSessionHas('success');
        $this->assertSame(2, FinanceExpense::count());
    }
}
