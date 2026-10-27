<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The date capital or a loan was actually received (it may be recorded days
 * later). Older entries keep NULL and count on the day they were recorded.
 * Live database: database/alters/0036_2026_10_27_finance_capital_received_at.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_capital_entries', function (Blueprint $table) {
            $table->date('received_at')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('finance_capital_entries', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
