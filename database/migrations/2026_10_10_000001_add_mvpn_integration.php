<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sell Mbunie VPN (vpn.mbuniehub.com) through the normal product/plan/order
 * flow: a product of type "vpn" whose plans map to MVPN plan codes. A
 * confirmed order is activated on the VPN control plane (signed partner API).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY type ENUM('subscription','software','vpn') NOT NULL DEFAULT 'subscription'");
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('type', ['subscription', 'software', 'vpn'])->default('subscription')->change();
            });
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->string('mvpn_plan_code', 40)->nullable()->after('duration_days');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('vpn_activated_at')->nullable()->after('software_access_expires_at');
            $table->timestamp('vpn_expires_at')->nullable()->after('vpn_activated_at');
            $table->unsignedSmallInteger('vpn_activation_attempts')->default(0)->after('vpn_expires_at');
            $table->text('vpn_activation_error')->nullable()->after('vpn_activation_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['vpn_activated_at', 'vpn_expires_at', 'vpn_activation_attempts', 'vpn_activation_error']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('mvpn_plan_code');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY type ENUM('subscription','software') NOT NULL DEFAULT 'subscription'");
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('type', ['subscription', 'software'])->default('subscription')->change();
            });
        }
    }
};
