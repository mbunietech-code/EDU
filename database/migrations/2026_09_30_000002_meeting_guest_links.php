<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest links: anyone with a room's link joins with just a name (no account),
 * waiting in the waiting room until the host lets them in. A guest is a
 * restricted user row (is_guest) tied to that one room. The whole feature is
 * off unless an admin turns it on (setting "learning.guest_links").
 *
 * Live database: database/alters/0021_2026_09_30_meeting_guest_links.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_rooms', function (Blueprint $table) {
            $table->string('guest_token', 40)->nullable()->unique();
            $table->boolean('guest_waiting_room')->default(true);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false);
            $table->unsignedBigInteger('guest_room_id')->nullable()->index();
            $table->timestamp('guest_admitted_at')->nullable();
            $table->timestamp('guest_denied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['guest_room_id']);
            $table->dropColumn(['is_guest', 'guest_room_id', 'guest_admitted_at', 'guest_denied_at']);
        });

        Schema::table('learning_rooms', function (Blueprint $table) {
            $table->dropUnique(['guest_token']);
            $table->dropColumn(['guest_token', 'guest_waiting_room']);
        });
    }
};
