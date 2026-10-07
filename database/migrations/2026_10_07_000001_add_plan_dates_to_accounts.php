<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mirrors database/alters/0031_2026_10_07_account_plan_dates.sql. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts') || Schema::hasColumn('accounts', 'expires_at')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('plan_name', 120)->nullable()->after('name');
            $table->date('purchased_at')->nullable()->after('plan_name');
            $table->date('expires_at')->nullable()->after('purchased_at');
            $table->decimal('cost', 12, 2)->nullable()->after('expires_at');
            $table->string('cost_currency', 3)->nullable()->after('cost');
            $table->boolean('auto_renew')->default(false)->after('cost_currency');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['plan_name', 'purchased_at', 'expires_at', 'cost', 'cost_currency', 'auto_renew']);
        });
    }
};
