<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors database/alters/0022_2026_10_04_learning_room_guests.sql.
 *
 * Guest links still use a restricted auth user for Laravel session compatibility,
 * but guest-facing room metadata now lives in its own table and real-user
 * queries exclude `users.is_guest`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_room_guests')) {
            Schema::create('learning_room_guests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('name', 60);
                $table->timestamp('admitted_at')->nullable();
                $table->timestamp('denied_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['learning_room_id', 'admitted_at', 'denied_at'], 'learning_room_guests_room_status_index');
                $table->index('last_seen_at', 'learning_room_guests_last_seen_at_index');
            });
        }

        if (Schema::hasColumn('users', 'is_guest')) {
            DB::table('users')
                ->where('is_guest', true)
                ->whereNotNull('guest_room_id')
                ->select(['id', 'guest_room_id', 'name', 'guest_admitted_at', 'guest_denied_at', 'last_seen_at', 'created_at', 'updated_at'])
                ->chunkById(500, function ($guests) {
                    foreach ($guests as $guest) {
                        DB::table('learning_room_guests')->updateOrInsert(
                            ['user_id' => $guest->id],
                            [
                                'learning_room_id' => $guest->guest_room_id,
                                'name' => $guest->name,
                                'admitted_at' => $guest->guest_admitted_at,
                                'denied_at' => $guest->guest_denied_at,
                                'last_seen_at' => $guest->last_seen_at,
                                'created_at' => $guest->created_at ?? now(),
                                'updated_at' => $guest->updated_at ?? now(),
                            ],
                        );
                    }
                }, 'id');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_room_guests');
    }
};
