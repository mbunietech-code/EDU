<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ITEM_COLUMNS = ['taxable_pay', 'nssf_employer', 'sdl_amount', 'wcf_amount', 'leave_provision', 'severance_provision', 'gratuity_provision', 'employer_cost'];

    private const PERIOD_COLUMNS = ['nssf_employer', 'sdl_amount', 'wcf_amount', 'leave_provision', 'severance_provision', 'gratuity_provision', 'total_provisions'];

    public function up(): void
    {
        foreach (['finance_payroll_items' => self::ITEM_COLUMNS, 'finance_payroll_periods' => self::PERIOD_COLUMNS] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        $table->decimal($column, 14, 2)->default(0);
                    }
                }
            });
        }

        if (! Schema::hasTable('finance_statutory_returns')) {
            Schema::create('finance_statutory_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finance_payroll_period_id')->constrained('finance_payroll_periods')->cascadeOnDelete();
                $table->string('type');
                $table->string('authority');
                $table->decimal('amount', 14, 2)->default(0);
                $table->date('due_date');
                $table->string('status')->default('pending');
                $table->date('paid_at')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['finance_payroll_period_id', 'type']);
                $table->index(['status', 'due_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_statutory_returns');
    }
};
