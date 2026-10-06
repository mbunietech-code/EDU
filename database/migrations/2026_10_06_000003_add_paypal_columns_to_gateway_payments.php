<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gateway_payments') || Schema::hasColumn('gateway_payments', 'charged_amount')) {
            return;
        }

        Schema::table('gateway_payments', function (Blueprint $table) {
            $table->decimal('charged_amount', 14, 2)->nullable()->after('currency');
            $table->string('charged_currency', 3)->nullable()->after('charged_amount');
            $table->string('payer_email')->nullable()->after('charged_currency');
            $table->text('redirect_url')->nullable()->after('payer_email');
        });
    }

    public function down(): void
    {
        Schema::table('gateway_payments', function (Blueprint $table) {
            $table->dropColumn(['charged_amount', 'charged_currency', 'payer_email', 'redirect_url']);
        });
    }
};
