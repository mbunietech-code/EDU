<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->enum('duration_type', ['days', 'lifetime'])
                ->default('days')
                ->after('duration_days')
                ->comment('days = fixed duration, lifetime = single purchase / no expiry');

            $table->integer('duration_days')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('duration_days')->nullable(false)->change();

            $table->dropColumn('duration_type');
        });
    }
};
