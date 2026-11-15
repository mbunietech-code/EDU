<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video help: a signed-in member asks for help on video; every support admin
 * is rung until one accepts, then both meet in the support live room.
 * Live MySQL gets the same table from database/alters/0041_*.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_help_requests')) {
            return;
        }

        Schema::create('video_help_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_room_id')->nullable()->constrained('learning_rooms')->nullOnDelete();
            // ringing | accepted | missed | cancelled
            $table->string('status', 16)->default('ringing');
            $table->string('reason', 500)->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('declined_by')->nullable(); // JSON list of admin ids who declined
            $table->timestamp('ring_until')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'ring_until']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_help_requests');
    }
};
