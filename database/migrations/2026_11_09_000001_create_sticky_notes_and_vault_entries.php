<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sticky notes on the admin dashboard (every admin and helpdesk member) and the
 * password vault (only people given "vault.view", behind a PIN). Vault secrets
 * are encrypted with APP_KEY. Live DB: database/alters/0038_*.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sticky_notes', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('color', 10)->default('yellow');
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vault_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 30)->default('other');
            $table->string('url', 500)->nullable();
            $table->text('username')->nullable();   // encrypted
            $table->text('password')->nullable();   // encrypted
            $table->text('notes')->nullable();      // encrypted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_entries');
        Schema::dropIfExists('sticky_notes');
    }
};
