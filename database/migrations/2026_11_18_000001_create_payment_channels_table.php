<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout channels (M-Pesa, Airtel Money, card, bank...) each served by one
 * gateway, switched on one by one and shown in the admin's priority order.
 * Rows are created by PaymentChannelService (off, unless their gateway was
 * already live). Live: database/alters/0046_2026_11_18_payment_channels.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_channels')) {
            return;
        }

        Schema::create('payment_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('kind', 20);
            $table->string('gateway', 40)->nullable();
            $table->string('network', 40)->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_channels');
    }
};
