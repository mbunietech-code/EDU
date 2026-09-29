<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Breakout rooms in the live classroom. Each breakout is its own SFU room
 * ("<provider_room>-b<n>"); the session holds how many there are and whether
 * they are running, each attendance holds the room its person is assigned to
 * (for a host: the room they are visiting), and chat messages remember the
 * room they were written in.
 *
 * Live database: database/alters/0020_2026_09_30_classroom_breakouts.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_room_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('breakout_count')->default(0);
            $table->boolean('breakouts_open')->default(false);
        });

        Schema::table('learning_room_attendances', function (Blueprint $table) {
            $table->unsignedTinyInteger('breakout_number')->nullable();
        });

        Schema::table('learning_room_messages', function (Blueprint $table) {
            $table->unsignedTinyInteger('breakout_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_room_messages', fn (Blueprint $table) => $table->dropColumn('breakout_number'));
        Schema::table('learning_room_attendances', fn (Blueprint $table) => $table->dropColumn('breakout_number'));
        Schema::table('learning_room_sessions', fn (Blueprint $table) => $table->dropColumn(['breakout_count', 'breakouts_open']));
    }
};
