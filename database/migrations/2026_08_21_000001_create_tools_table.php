<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('version')->nullable();
            $table->string('license_key')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
        });

        Schema::create('tool_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_filename');
            $table->string('file_type');
            $table->integer('file_size')->nullable()->comment('Size in bytes');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tool_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_downloads');
        Schema::dropIfExists('tools');
    }
};
