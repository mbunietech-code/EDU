<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_expenses')) {
            return;
        }

        if (Schema::hasColumn('finance_expenses', 'receipt_path')) {
            return;
        }

        Schema::table('finance_expenses', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('finance_expenses', 'receipt_path')) {
            Schema::table('finance_expenses', function (Blueprint $table) {
                $table->dropColumn('receipt_path');
            });
        }
    }
};
