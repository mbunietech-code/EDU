<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROOM — live classroom & video learning. Mirrors
 * database/alters/0014_2026_09_24_learning_rooms_and_videos.sql; every step is
 * guarded so it is a no-op on a live database that already received the alter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'can_teach')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_teach')->default(false)->after('can_write_research');
            });
        }

        if (! Schema::hasTable('learning_categories')) {
            Schema::create('learning_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique('learning_categories_slug_unique');
                $table->string('description', 500)->nullable();
                $table->string('icon', 60)->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index('position', 'learning_categories_position_index');
            });
        }

        if (! Schema::hasTable('learning_courses')) {
            Schema::create('learning_courses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_category_id')->constrained('learning_categories')->restrictOnDelete();
                $table->string('title');
                $table->string('slug')->unique('learning_courses_slug_unique');
                $table->string('summary', 1000)->nullable();
                $table->text('description')->nullable();           // Markdown
                $table->string('thumbnail_path')->nullable();
                $table->string('level', 20)->nullable();           // beginner | intermediate | advanced
                $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('draft');    // draft | published
                $table->string('access', 20)->default('open');     // open | enrolled
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'learning_category_id'], 'learning_courses_status_category_index');
                $table->index('instructor_id', 'learning_courses_instructor_index');
            });
        }

        if (! Schema::hasTable('learning_topics')) {
            Schema::create('learning_topics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
                $table->string('title');
                $table->string('description', 500)->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['learning_course_id', 'position'], 'learning_topics_course_position_index');
            });
        }

        if (! Schema::hasTable('learning_videos')) {
            Schema::create('learning_videos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_category_id')->constrained('learning_categories')->restrictOnDelete();
                $table->foreignId('learning_course_id')->nullable()->constrained('learning_courses')->nullOnDelete();
                $table->foreignId('learning_topic_id')->nullable()->constrained('learning_topics')->nullOnDelete();
                $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->string('slug')->unique('learning_videos_slug_unique');
                $table->text('description')->nullable();            // Markdown
                $table->json('tags')->nullable();
                $table->string('visibility', 20)->default('course'); // course | members | private
                $table->string('status', 20)->default('draft');      // draft | published
                $table->timestamp('published_at')->nullable();
                $table->string('disk', 30)->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->string('thumbnail_path')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'learning_category_id'], 'learning_videos_status_category_index');
                $table->index(['learning_course_id', 'learning_topic_id', 'position'], 'learning_videos_course_topic_position_index');
                $table->index('instructor_id', 'learning_videos_instructor_index');
                $table->index('published_at', 'learning_videos_published_at_index');
            });
        }

        if (! Schema::hasTable('learning_video_renditions')) {
            Schema::create('learning_video_renditions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_video_id')->constrained('learning_videos')->cascadeOnDelete();
                $table->string('quality', 10);                       // 360p | 480p | 720p | 1080p
                $table->unsignedSmallInteger('height');
                $table->string('disk', 30);
                $table->string('path');
                $table->string('mime', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->timestamps();

                $table->unique(['learning_video_id', 'quality'], 'learning_video_renditions_video_quality_unique');
            });
        }

        if (! Schema::hasTable('learning_video_resources')) {
            Schema::create('learning_video_resources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_video_id')->constrained('learning_videos')->cascadeOnDelete();
                $table->string('title');
                $table->string('type', 10);                          // file | link
                $table->string('url', 500)->nullable();
                $table->string('disk', 30)->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['learning_video_id', 'position'], 'learning_video_resources_video_position_index');
            });
        }

        if (! Schema::hasTable('learning_video_progress')) {
            Schema::create('learning_video_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_video_id')->constrained('learning_videos')->cascadeOnDelete();
                $table->unsignedInteger('position_seconds')->default(0);
                $table->unsignedInteger('max_position_seconds')->default(0);
                $table->unsignedInteger('watched_seconds')->default(0);
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedTinyInteger('percent')->default(0);
                $table->unsignedInteger('play_count')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_watched_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'learning_video_id'], 'learning_video_progress_user_video_unique');
                $table->index(['user_id', 'last_watched_at'], 'learning_video_progress_user_last_watched_index');
                $table->index(['learning_video_id', 'completed_at'], 'learning_video_progress_video_completed_index');
            });
        }

        if (! Schema::hasTable('learning_video_comments')) {
            Schema::create('learning_video_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_video_id')->constrained('learning_videos')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('learning_video_comments')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['learning_video_id', 'parent_id', 'id'], 'learning_video_comments_video_parent_id_index');
            });
        }

        if (! Schema::hasTable('learning_enrollments')) {
            Schema::create('learning_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
                $table->string('source', 20)->default('self');       // self | admin
                $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('enrolled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'learning_course_id'], 'learning_enrollments_user_course_unique');
                $table->index('learning_course_id', 'learning_enrollments_course_index');
            });
        }

        if (! Schema::hasTable('learning_rooms')) {
            Schema::create('learning_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique('learning_rooms_slug_unique');
                $table->text('description')->nullable();
                $table->foreignId('learning_category_id')->nullable()->constrained('learning_categories')->nullOnDelete();
                $table->foreignId('learning_course_id')->nullable()->constrained('learning_courses')->nullOnDelete();
                $table->foreignId('learning_topic_id')->nullable()->constrained('learning_topics')->nullOnDelete();
                $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('draft');      // draft | scheduled | live | completed | cancelled
                $table->string('access', 20)->default('public');     // public | private | category | course
                $table->timestamp('scheduled_at')->nullable();
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->string('provider_room', 120)->unique('learning_rooms_provider_room_unique');
                $table->boolean('chat_enabled')->default(true);
                $table->boolean('questions_enabled')->default(true);
                $table->boolean('allow_participant_media')->default(true);
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'scheduled_at'], 'learning_rooms_status_scheduled_at_index');
                $table->index('host_id', 'learning_rooms_host_index');
            });
        }

        if (! Schema::hasTable('learning_room_members')) {
            Schema::create('learning_room_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['learning_room_id', 'user_id'], 'learning_room_members_room_user_unique');
            });
        }

        if (! Schema::hasTable('learning_room_sessions')) {
            Schema::create('learning_room_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedSmallInteger('peak_participants')->default(0);
                $table->timestamps();

                $table->index(['learning_room_id', 'started_at'], 'learning_room_sessions_room_started_at_index');
            });
        }

        if (! Schema::hasTable('learning_room_attendances')) {
            Schema::create('learning_room_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_session_id')->constrained('learning_room_sessions')->cascadeOnDelete();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 20)->default('participant');  // host | participant
                $table->timestamp('first_joined_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->unsignedInteger('total_seconds')->default(0);
                $table->unsignedSmallInteger('join_count')->default(0);
                $table->timestamp('removed_at')->nullable();
                $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('jitsi_participant_id', 64)->nullable();
                $table->timestamps();

                $table->unique(['learning_room_session_id', 'user_id'], 'learning_room_attendances_session_user_unique');
                $table->index(['learning_room_id', 'user_id'], 'learning_room_attendances_room_user_index');
            });
        }

        if (! Schema::hasTable('learning_room_messages')) {
            Schema::create('learning_room_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->foreignId('learning_room_session_id')->nullable()->constrained('learning_room_sessions')->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20)->default('chat');         // chat | question | announcement
                $table->text('body');
                $table->boolean('is_answered')->default(false);
                $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('answered_at')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();

                $table->index(['learning_room_id', 'id'], 'learning_room_messages_room_id_index');
                $table->index(['learning_room_id', 'updated_at'], 'learning_room_messages_room_updated_at_index');
            });
        }

        if (! Schema::hasTable('learning_room_recordings')) {
            Schema::create('learning_room_recordings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_room_id')->constrained('learning_rooms')->cascadeOnDelete();
                $table->foreignId('learning_room_session_id')->nullable()->constrained('learning_room_sessions')->nullOnDelete();
                $table->string('source', 20);                        // upload | livekit
                $table->string('status', 20)->default('ready');      // processing | ready | failed
                $table->string('disk', 30)->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->string('external_id', 191)->nullable()->unique('learning_room_recordings_external_id_unique');
                $table->text('external_url')->nullable();
                $table->text('error')->nullable();
                $table->boolean('is_shared')->default(false);
                $table->foreignId('learning_video_id')->nullable()->constrained('learning_videos')->nullOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('learning_room_id', 'learning_room_recordings_room_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_room_recordings');
        Schema::dropIfExists('learning_room_messages');
        Schema::dropIfExists('learning_room_attendances');
        Schema::dropIfExists('learning_room_sessions');
        Schema::dropIfExists('learning_room_members');
        Schema::dropIfExists('learning_rooms');
        Schema::dropIfExists('learning_enrollments');
        Schema::dropIfExists('learning_video_comments');
        Schema::dropIfExists('learning_video_progress');
        Schema::dropIfExists('learning_video_resources');
        Schema::dropIfExists('learning_video_renditions');
        Schema::dropIfExists('learning_videos');
        Schema::dropIfExists('learning_topics');
        Schema::dropIfExists('learning_courses');
        Schema::dropIfExists('learning_categories');

        if (Schema::hasColumn('users', 'can_teach')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_teach'));
        }
    }
};
