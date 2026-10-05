<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_payroll_periods')) {
            Schema::table('finance_payroll_periods', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_payroll_periods', 'basic_pay')) {
                    $table->decimal('basic_pay', 14, 2)->default(0)->after('staff_count');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'total_allowances')) {
                    $table->decimal('total_allowances', 14, 2)->default(0)->after('basic_pay');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'overtime_pay')) {
                    $table->decimal('overtime_pay', 14, 2)->default(0)->after('total_allowances');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'bonus_pay')) {
                    $table->decimal('bonus_pay', 14, 2)->default(0)->after('overtime_pay');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'taxable_pay')) {
                    $table->decimal('taxable_pay', 14, 2)->default(0)->after('gross_pay');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'tax_amount')) {
                    $table->decimal('tax_amount', 14, 2)->default(0)->after('total_deductions');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'pension_amount')) {
                    $table->decimal('pension_amount', 14, 2)->default(0)->after('tax_amount');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'insurance_amount')) {
                    $table->decimal('insurance_amount', 14, 2)->default(0)->after('pension_amount');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'loan_deductions')) {
                    $table->decimal('loan_deductions', 14, 2)->default(0)->after('insurance_amount');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'other_deductions')) {
                    $table->decimal('other_deductions', 14, 2)->default(0)->after('loan_deductions');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'employer_cost')) {
                    $table->decimal('employer_cost', 14, 2)->default(0)->after('net_pay');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'notes')) {
                    $table->text('notes')->nullable()->after('employer_cost');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'prepared_at')) {
                    $table->timestamp('prepared_at')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('prepared_at');
                }
                if (! Schema::hasColumn('finance_payroll_periods', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('approved_at');
                }
            });
        }

        if (! Schema::hasTable('finance_payroll_items')) {
            Schema::create('finance_payroll_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finance_payroll_period_id')->constrained('finance_payroll_periods')->cascadeOnDelete();
                $table->foreignId('finance_staff_id')->nullable()->constrained('finance_staff')->nullOnDelete();
                $table->string('staff_number')->nullable();
                $table->string('staff_name');
                $table->string('department_name')->nullable();
                $table->string('position_name')->nullable();
                $table->decimal('basic_pay', 14, 2)->default(0);
                $table->decimal('allowances', 14, 2)->default(0);
                $table->decimal('overtime_pay', 14, 2)->default(0);
                $table->decimal('bonus_pay', 14, 2)->default(0);
                $table->decimal('gross_pay', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('pension_amount', 14, 2)->default(0);
                $table->decimal('insurance_amount', 14, 2)->default(0);
                $table->decimal('loan_deduction', 14, 2)->default(0);
                $table->decimal('other_deduction', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('net_pay', 14, 2)->default(0);
                $table->string('payment_channel')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_account_number')->nullable();
                $table->string('mobile_money')->nullable();
                $table->timestamps();
                $table->unique(['finance_payroll_period_id', 'finance_staff_id'], 'finance_payroll_staff_unique');
                $table->index(['finance_payroll_period_id', 'department_name'], 'finance_payroll_items_period_department_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payroll_items');
    }
};
