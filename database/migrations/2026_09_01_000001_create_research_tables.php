<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'can_write_research')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_write_research')->default(false)->after('permissions');
            });
        }

        if (! Schema::hasTable('research_categories')) {
            Schema::create('research_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description', 500)->nullable();
                $table->string('icon', 60)->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('researches')) {
            Schema::create('researches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_category_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // author
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('summary', 1000)->nullable();
                $table->string('cover_image')->nullable();
                // draft | submitted | under_review | changes_requested | approved | published | archived
                $table->string('status', 30)->default('draft');
                $table->text('review_note')->nullable();       // latest admin feedback
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedInteger('views')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'research_category_id']);
            });
        }

        if (! Schema::hasTable('research_chapters')) {
            Schema::create('research_chapters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_id')->constrained('researches')->cascadeOnDelete();
                $table->string('title');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['research_id', 'position']);
            });
        }

        if (! Schema::hasTable('research_sections')) {
            Schema::create('research_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_chapter_id')->constrained()->cascadeOnDelete();
                $table->string('heading');
                $table->longText('body')->nullable();          // Markdown
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['research_chapter_id', 'position']);
            });
        }

        if (! Schema::hasTable('research_reviews')) {
            Schema::create('research_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_id')->constrained('researches')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 30); // submitted | approved | rejected | changes_requested | published
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->index('research_id');
            });
        }

        if (! Schema::hasTable('research_reading_progress')) {
            Schema::create('research_reading_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('research_id')->constrained('researches')->cascadeOnDelete();
                $table->foreignId('last_section_id')->nullable()->constrained('research_sections')->nullOnDelete();
                $table->unsignedTinyInteger('percent')->default(0);
                $table->json('done_section_ids')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'research_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_reading_progress');
        Schema::dropIfExists('research_reviews');
        Schema::dropIfExists('research_sections');
        Schema::dropIfExists('research_chapters');
        Schema::dropIfExists('researches');
        Schema::dropIfExists('research_categories');

        if (Schema::hasColumn('users', 'can_write_research')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_write_research'));
        }
    }
};
