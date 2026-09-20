<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires a covering index on a foreign-keyed column at all
        // times, so the replacement plain index must exist before the old
        // unique index (currently the only one covering user_id) is dropped.
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->index('user_id', 'ai_conversations_user_id_index');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropUnique('ai_conversations_user_id_unique');
            $table->string('title')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unique('user_id', 'ai_conversations_user_id_unique');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropIndex('ai_conversations_user_id_index');
            $table->dropColumn('title');
        });
    }
};
