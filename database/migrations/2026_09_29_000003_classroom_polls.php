<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polls and quick quizzes inside a live class. The host asks a question with
 * 2–6 options (optionally marking the right one); each participant votes once
 * while it is open; everyone sees the results when the host closes it.
 *
 * Live database: database/alters/0018_2026_09_29_classroom_polls.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_room_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
            $table->foreignId('learning_room_session_id')->nullable()->constrained('learning_room_sessions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 300);
            $table->json('options');
            $table->unsignedTinyInteger('correct_option')->nullable(); // quiz: index into options
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['learning_room_id', 'learning_room_session_id']);
        });

        Schema::create('learning_room_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_room_poll_id')->constrained('learning_room_polls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('choice');
            $table->timestamps();

            $table->unique(['learning_room_poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_room_poll_votes');
        Schema::dropIfExists('learning_room_polls');
    }
};
