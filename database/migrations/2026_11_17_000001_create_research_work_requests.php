<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Research work requests: a customer orders research work (thesis chapter,
 * proposal, data analysis…), describes it, and admins run it through a
 * configurable process (stages) until it is delivered and closed.
 *
 * Not the same as `researches` (research a member writes and publishes).
 * Live MySQL gets the same tables from database/alters/0044_*.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research_request_stages')) {
            Schema::create('research_request_stages', function (Blueprint $table) {
                $table->id();
                // System stages the workflow moves to by itself (received, quoted, revisions, delivered, closed); null = added by an admin.
                $table->string('key', 32)->nullable()->unique();
                $table->string('name', 80);
                $table->string('description', 255)->nullable();
                $table->string('color', 16)->default('indigo');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('allows_cancel')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });

            $now = now();
            DB::table('research_request_stages')->insert(array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], [
                ['key' => 'received', 'name' => 'Received', 'description' => 'We have your request.', 'color' => 'gray', 'progress' => 5, 'sort_order' => 1, 'allows_cancel' => true, 'is_active' => true],
                ['key' => 'reviewing', 'name' => 'Reviewing', 'description' => 'Our team is studying the brief.', 'color' => 'sky', 'progress' => 10, 'sort_order' => 2, 'allows_cancel' => true, 'is_active' => true],
                ['key' => 'quoted', 'name' => 'Quoted', 'description' => 'Price sent, waiting for your answer.', 'color' => 'amber', 'progress' => 20, 'sort_order' => 3, 'allows_cancel' => true, 'is_active' => true],
                ['key' => 'in_progress', 'name' => 'In progress', 'description' => 'The work is being done.', 'color' => 'indigo', 'progress' => 40, 'sort_order' => 4, 'allows_cancel' => false, 'is_active' => true],
                ['key' => 'draft_ready', 'name' => 'Draft ready', 'description' => 'A draft is ready for you to read.', 'color' => 'violet', 'progress' => 70, 'sort_order' => 5, 'allows_cancel' => false, 'is_active' => true],
                ['key' => 'revisions', 'name' => 'Revisions', 'description' => 'Changes you asked for are being made.', 'color' => 'orange', 'progress' => 80, 'sort_order' => 6, 'allows_cancel' => false, 'is_active' => true],
                ['key' => 'delivered', 'name' => 'Delivered', 'description' => 'Final work delivered. Accept it or ask for changes.', 'color' => 'emerald', 'progress' => 95, 'sort_order' => 7, 'allows_cancel' => false, 'is_active' => true],
                ['key' => 'closed', 'name' => 'Closed', 'description' => 'Finished.', 'color' => 'green', 'progress' => 100, 'sort_order' => 8, 'allows_cancel' => false, 'is_active' => true],
            ]));
        }

        if (! Schema::hasTable('research_requests')) {
            Schema::create('research_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 20)->nullable()->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title', 200);
                $table->string('research_type', 40);
                $table->string('field_of_study', 120)->nullable();
                $table->text('description');
                $table->string('academic_level', 30);
                $table->string('purpose', 200)->nullable();
                $table->date('deadline')->nullable();
                $table->decimal('budget', 12, 2)->nullable();
                $table->string('currency', 3)->default('TZS');
                $table->unsignedInteger('pages')->nullable();
                $table->string('citation_style', 20)->nullable();
                $table->string('priority', 10)->default('normal');
                $table->foreignId('stage_id')->nullable()->constrained('research_request_stages')->nullOnDelete();
                // active | completed | cancelled
                $table->string('status', 12)->default('active');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('quote_amount', 12, 2)->nullable();
                $table->string('quote_currency', 3)->nullable();
                $table->text('quote_note')->nullable();
                // null | pending | accepted | declined
                $table->string('quote_status', 10)->nullable();
                $table->timestamp('quoted_at')->nullable();
                $table->timestamp('quote_answered_at')->nullable();
                $table->unsignedSmallInteger('revision_count')->default(0);
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason', 500)->nullable();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('review')->nullable();
                $table->timestamp('customer_activity_at')->nullable();
                $table->timestamp('admin_activity_at')->nullable();
                $table->timestamp('customer_seen_at')->nullable();
                $table->timestamp('admin_seen_at')->nullable();
                $table->date('deadline_alerted_on')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'id']);
                $table->index(['status', 'stage_id']);
                $table->index(['status', 'deadline']);
                $table->index('assigned_to');
            });
        }

        if (! Schema::hasTable('research_request_messages')) {
            Schema::create('research_request_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('is_from_admin')->default(false);
                // Internal note: staff only, never shown or sent to the customer.
                $table->boolean('is_internal')->default(false);
                $table->text('body')->nullable();
                $table->timestamps();

                $table->index(['research_request_id', 'id']);
            });
        }

        if (! Schema::hasTable('research_request_files')) {
            Schema::create('research_request_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('research_request_messages')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                // brief | message | draft | final
                $table->string('kind', 10)->default('brief');
                $table->boolean('is_internal')->default(false);
                $table->string('path', 255);
                $table->string('original_name', 255);
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->index(['research_request_id', 'kind']);
            });
        }

        if (! Schema::hasTable('research_request_events')) {
            Schema::create('research_request_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 30);
                $table->foreignId('from_stage_id')->nullable()->constrained('research_request_stages')->nullOnDelete();
                $table->foreignId('to_stage_id')->nullable()->constrained('research_request_stages')->nullOnDelete();
                $table->unsignedTinyInteger('progress')->nullable();
                // Shown to the customer on their timeline.
                $table->text('note')->nullable();
                // Staff only.
                $table->text('internal_note')->nullable();
                // Whole event hidden from the customer (e.g. assignment).
                $table->boolean('is_internal')->default(false);
                $table->text('meta')->nullable();
                $table->timestamps();

                $table->index(['research_request_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_request_events');
        Schema::dropIfExists('research_request_files');
        Schema::dropIfExists('research_request_messages');
        Schema::dropIfExists('research_requests');
        Schema::dropIfExists('research_request_stages');
    }
};
