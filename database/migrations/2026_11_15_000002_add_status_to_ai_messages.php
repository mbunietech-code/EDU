<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Assistant chat (DeepSeek): a reply is written while it is generated, so the
 * page can poll it and show the text bit by bit. status = streaming | done |
 * stopped | error (null = older rows, treated as done). meta = small JSON
 * (provider, model, token usage, error text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_messages', 'status')) {
                $table->string('status', 16)->nullable()->after('tools_used');
            }
            if (! Schema::hasColumn('ai_messages', 'meta')) {
                $table->text('meta')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['status', 'meta']);
        });
    }
};
