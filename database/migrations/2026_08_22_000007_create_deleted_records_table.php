<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_records', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('label');
            $table->text('reason');
            $table->json('snapshot')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('entity');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('payment_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::dropIfExists('deleted_records');
    }
};
