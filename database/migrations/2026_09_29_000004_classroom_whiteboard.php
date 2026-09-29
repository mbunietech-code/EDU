<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared whiteboard in the live classroom, one per session (a new class starts
 * with a clean board). Strokes travel live over SFU data packets and are
 * stored here so late joiners see the board; board_version goes up on clear
 * or undo so clients reload it.
 *
 * Live database: database/alters/0019_2026_09_29_classroom_whiteboard.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_room_sessions', function (Blueprint $table) {
            $table->boolean('board_active')->default(false);
            $table->boolean('board_all_can_draw')->default(false);
            $table->unsignedInteger('board_version')->default(0);
        });

        Schema::create('learning_room_board_strokes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_room_session_id')->constrained('learning_room_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 40); // the drawer's own id for the stroke (matches the live packets)
            $table->json('data');      // {c: colour, w: width (1/1000 of board width), p: [x, y, …] in 0..10000}
            $table->timestamps();

            $table->unique(['learning_room_session_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_room_board_strokes');

        Schema::table('learning_room_sessions', function (Blueprint $table) {
            $table->dropColumn(['board_active', 'board_all_can_draw', 'board_version']);
        });
    }
};
