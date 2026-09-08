<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_conversations', function (Blueprint $table) {
            $table->id();
            // The non-super-admin participant. Their whole "Super Admin" side
            // is treated as one collective counterpart (see AdminMessage) so
            // there's exactly one thread per admin, not one per admin-pair.
            // NOTE: ->unique() chained after ->constrained() lands on the
            // foreign-key clause, not the column, and is silently ignored —
            // the uniqueness has to be declared as its own index below.
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->unique('admin_id');
            $table->timestamps();
        });

        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
        Schema::dropIfExists('admin_conversations');
    }
};
