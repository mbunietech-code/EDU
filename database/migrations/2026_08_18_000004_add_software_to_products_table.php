<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['subscription', 'software'])->default('subscription')->after('status');
            $table->string('software_file')->nullable()->after('type');
            $table->string('software_filename')->nullable()->after('software_file');
            $table->string('software_version')->nullable()->after('software_filename');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['type', 'software_file', 'software_filename', 'software_version']);
        });
    }
};