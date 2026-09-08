<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
            $table->boolean('is_deleted')->default(false)->after('edited_at');
        });

        Schema::table('admin_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
            $table->boolean('is_deleted')->default(false)->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'is_deleted']);
        });

        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'is_deleted']);
        });
    }
};
