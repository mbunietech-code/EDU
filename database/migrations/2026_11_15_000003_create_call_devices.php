<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call devices: a support admin's computer (browser Web Push) or phone (MHub
 * app, FCM) that rings for video help calls even while signed out.
 * Live MySQL gets the same table from database/alters/0043_*.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_devices')) {
            return;
        }

        Schema::create('call_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16); // webpush | fcm
            $table->string('name', 120)->nullable();
            $table->string('platform', 32)->nullable();
            $table->text('token'); // Web Push endpoint URL or FCM registration token
            $table->char('token_hash', 64)->unique();
            $table->timestamp('test_until')->nullable();
            $table->timestamp('last_rung_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_devices');
    }
};
