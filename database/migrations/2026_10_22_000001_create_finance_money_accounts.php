<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money accounts ("mifuko"): where the money actually sits (Alipay, WeChat,
 * MIX by YAS, CRDB, petty cash…), transfers between them, and which one each
 * expense, capital entry and loan repayment went through. Additive: existing
 * rows keep money_account_id = null and show as "not assigned" until an admin
 * picks one.
 *
 * Live database: database/alters/0034_2026_10_22_finance_money_accounts.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_money_accounts')) {
            Schema::create('finance_money_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('type', 20)->default('mobile_money');
                $table->string('number', 120)->nullable();
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->date('opening_date')->nullable();
                $table->json('payment_methods')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finance_money_transfers')) {
            Schema::create('finance_money_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('from_account_id')->constrained('finance_money_accounts')->cascadeOnDelete();
                $table->foreignId('to_account_id')->constrained('finance_money_accounts')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->date('transferred_at');
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        foreach (['finance_expenses', 'finance_capital_entries', 'finance_loan_repayments'] as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'money_account_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreignId('money_account_id')->nullable()->after('id')
                        ->constrained('finance_money_accounts')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['finance_loan_repayments', 'finance_capital_entries', 'finance_expenses'] as $name) {
            if (Schema::hasColumn($name, 'money_account_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('money_account_id'));
            }
        }
        Schema::dropIfExists('finance_money_transfers');
        Schema::dropIfExists('finance_money_accounts');
    }
};
