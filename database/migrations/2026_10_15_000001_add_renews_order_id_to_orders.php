<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer renewals: a renewal is a normal order that points at the order it
 * renews. Approving it extends that order's subscription (same account) or,
 * for VPN, adds the plan's days on the VPN side.
 *
 * Live database: database/alters/0033_2026_10_15_order_renewals.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('renews_order_id')->nullable()->after('plan_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renews_order_id');
        });
    }
};
