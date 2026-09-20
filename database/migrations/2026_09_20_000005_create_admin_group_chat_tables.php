<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_group_id')->constrained('admin_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Highest message id this member has seen — drives their unread count.
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamps();

            $table->unique(['admin_group_id', 'user_id']);
        });

        Schema::create('admin_group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_group_id')->constrained('admin_groups')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['admin_group_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_group_messages');
        Schema::dropIfExists('admin_group_members');
        Schema::dropIfExists('admin_groups');
    }
};
