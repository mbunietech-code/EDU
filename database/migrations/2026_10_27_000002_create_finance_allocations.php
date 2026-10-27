<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money already in an account, given to one line of the Finance overview
 * (a software, a cost type or a name) as its capital. Money does not move:
 * the account balance and the company's total stay the same.
 * Shared hosting: database/alters/0037_2026_10_27_finance_allocations.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_allocations')) {
            return;
        }

        Schema::create('finance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('money_account_id')->constrained('finance_money_accounts')->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('tool_id')->nullable()->constrained('tools')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('allocated_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_allocations');
    }
};
