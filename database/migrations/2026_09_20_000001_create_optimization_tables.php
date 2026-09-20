<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optimization_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_tables')->default(0);
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->decimal('total_size_mb', 12, 2)->default(0);
            $table->unsignedBigInteger('expired_count')->default(0);
            $table->unsignedBigInteger('expiring_soon_count')->default(0);
            $table->unsignedBigInteger('inactive_count')->default(0);
            $table->unsignedBigInteger('duplicate_count')->default(0);
            $table->unsignedInteger('missing_index_count')->default(0);
            $table->unsignedInteger('slow_query_count')->default(0);
            $table->unsignedBigInteger('orphaned_count')->default(0);
            $table->decimal('estimated_recovery_mb', 12, 2)->default(0);
            $table->json('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('optimization_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('optimization_scans')->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('operation', 20)->default('none');
            $table->string('table_name');
            $table->string('column_name')->nullable();
            $table->unsignedBigInteger('affected_count')->default(0);
            $table->string('expiration_status')->nullable();
            $table->text('action');
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->decimal('estimated_recovery_mb', 12, 2)->nullable();
            $table->text('where_sql')->nullable();
            $table->json('where_bindings')->nullable();
            $table->json('update_values')->nullable();
            $table->text('index_sql')->nullable();
            $table->text('sql_preview');
            $table->text('rollback_note');
            $table->enum('status', ['pending', 'approved', 'rejected', 'executed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedBigInteger('executed_count')->nullable();
            $table->string('backup_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('scan_id');
            $table->index('status');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimization_recommendations');
        Schema::dropIfExists('optimization_scans');
    }
};
