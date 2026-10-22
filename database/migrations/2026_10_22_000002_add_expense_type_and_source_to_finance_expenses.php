<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense types (umeme, bundles, kodi…) and a link to what created an expense
 * automatically (payroll, statutory return, product account, company asset),
 * so the same cost is never recorded twice. Existing rows keep NULL.
 *
 * Live database: database/alters/0035_2026_10_22_finance_expense_types.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('finance_expenses', 'expense_type')) {
            return;
        }

        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->string('expense_type', 40)->nullable()->after('category')->index();
            $table->string('source_type', 40)->nullable()->after('expense_type');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'finance_expenses_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->dropIndex('finance_expenses_source_index');
            $table->dropIndex(['expense_type']);
            $table->dropColumn(['expense_type', 'source_type', 'source_id']);
        });
    }
};
