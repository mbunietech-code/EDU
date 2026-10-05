<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_departments')) {
            Schema::create('finance_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_employment_types')) {
            Schema::create('finance_employment_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_positions')) {
            Schema::create('finance_positions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finance_department_id')->nullable()->constrained('finance_departments')->nullOnDelete();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->string('salary_grade')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_staff')) {
            Schema::create('finance_staff', function (Blueprint $table) {
                $table->id();
                $table->string('staff_number')->unique();
                $table->string('first_name');
                $table->string('middle_name')->nullable();
                $table->string('last_name');
                $table->string('gender')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('national_id')->nullable();
                $table->string('phone')->nullable();
                $table->string('alternative_phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('region')->nullable();
                $table->string('district')->nullable();
                $table->string('emergency_contact')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->foreignId('finance_department_id')->nullable()->constrained('finance_departments')->nullOnDelete();
                $table->foreignId('finance_position_id')->nullable()->constrained('finance_positions')->nullOnDelete();
                $table->foreignId('finance_employment_type_id')->nullable()->constrained('finance_employment_types')->nullOnDelete();
                $table->date('hire_date')->nullable();
                $table->string('status')->default('active');
                $table->decimal('basic_salary', 14, 2)->default(0);
                $table->string('bank_name')->nullable();
                $table->string('bank_account_number')->nullable();
                $table->string('mobile_money')->nullable();
                $table->string('tax_number')->nullable();
                $table->string('pension_number')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'finance_department_id']);
            });
        }

        if (! Schema::hasTable('finance_staff_contracts')) {
            Schema::create('finance_staff_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finance_staff_id')->constrained('finance_staff')->cascadeOnDelete();
                $table->foreignId('finance_department_id')->nullable()->constrained('finance_departments')->nullOnDelete();
                $table->foreignId('finance_position_id')->nullable()->constrained('finance_positions')->nullOnDelete();
                $table->foreignId('finance_employment_type_id')->nullable()->constrained('finance_employment_types')->nullOnDelete();
                $table->string('contract_number')->unique();
                $table->string('contract_type')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->decimal('basic_salary', 14, 2)->default(0);
                $table->string('salary_grade')->nullable();
                $table->unsignedSmallInteger('leave_entitlement_days')->nullable();
                $table->string('status')->default('draft');
                $table->date('renewal_date')->nullable();
                $table->date('termination_date')->nullable();
                $table->text('termination_reason')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'end_date']);
            });
        }

        if (! Schema::hasTable('finance_payroll_periods')) {
            Schema::create('finance_payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->date('period_month');
                $table->string('status')->default('draft');
                $table->unsignedInteger('staff_count')->default(0);
                $table->decimal('gross_pay', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('net_pay', 14, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique('period_month');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payroll_periods');
        Schema::dropIfExists('finance_staff_contracts');
        Schema::dropIfExists('finance_staff');
        Schema::dropIfExists('finance_positions');
        Schema::dropIfExists('finance_employment_types');
        Schema::dropIfExists('finance_departments');
    }
};
