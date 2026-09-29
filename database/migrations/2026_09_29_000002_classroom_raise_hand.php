<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raise hand in the live classroom: when this participant raised their hand
 * in the current session (NULL = hand down). Lowered on leave and by the host.
 *
 * Live database: database/alters/0017_2026_09_29_classroom_raise_hand.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_room_attendances', function (Blueprint $table) {
            $table->timestamp('hand_raised_at')->nullable()->after('can_share_screen');
        });
    }

    public function down(): void
    {
        Schema::table('learning_room_attendances', function (Blueprint $table) {
            $table->dropColumn('hand_raised_at');
        });
    }
};
