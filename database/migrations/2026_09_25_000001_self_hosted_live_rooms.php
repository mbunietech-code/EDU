<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-hosted live rooms (LiveKit SFU + coturn) replace the Jitsi/JaaS
 * integration:
 *  - rooms: lock + participant screen-share switch,
 *  - attendances: per-participant mic / camera / screen overrides
 *    (NULL = follow the room setting); the Jitsi participant id is dropped,
 *  - sessions: the running Egress (recording) id,
 *  - learning_room_materials: files the host shares in the classroom.
 *
 * Live database: database/alters/0015_2026_09_25_self_hosted_live_rooms.sql
 * (additive only — the unused jitsi_participant_id column stays there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_rooms', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('allow_participant_media');
            }
            if (! Schema::hasColumn('learning_rooms', 'allow_screen_share')) {
                $table->boolean('allow_screen_share')->default(false)->after('allow_participant_media');
            }
        });

        Schema::table('learning_room_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_room_attendances', 'can_publish_audio')) {
                $table->boolean('can_publish_audio')->nullable()->after('removed_by');
            }
            if (! Schema::hasColumn('learning_room_attendances', 'can_publish_video')) {
                $table->boolean('can_publish_video')->nullable()->after('can_publish_audio');
            }
            if (! Schema::hasColumn('learning_room_attendances', 'can_share_screen')) {
                $table->boolean('can_share_screen')->nullable()->after('can_publish_video');
            }
        });

        if (Schema::hasColumn('learning_room_attendances', 'jitsi_participant_id')) {
            Schema::table('learning_room_attendances', function (Blueprint $table) {
                $table->dropColumn('jitsi_participant_id');
            });
        }

        Schema::table('learning_room_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_room_sessions', 'egress_id')) {
                $table->string('egress_id', 100)->nullable()->after('peak_participants');
            }
            if (! Schema::hasColumn('learning_room_sessions', 'recording_started_at')) {
                $table->timestamp('recording_started_at')->nullable()->after('egress_id');
            }
        });

        if (! Schema::hasTable('learning_room_materials')) {
            Schema::create('learning_room_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->string('title');
                $table->string('disk', 30);
                $table->string('path');
                $table->string('original_name');
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('learning_room_id', 'learning_room_materials_room_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_room_materials');

        Schema::table('learning_room_sessions', function (Blueprint $table) {
            $table->dropColumn(['egress_id', 'recording_started_at']);
        });

        Schema::table('learning_room_attendances', function (Blueprint $table) {
            $table->dropColumn(['can_publish_audio', 'can_publish_video', 'can_share_screen']);
            $table->string('jitsi_participant_id', 64)->nullable();
        });

        Schema::table('learning_rooms', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'allow_screen_share']);
        });
    }
};
