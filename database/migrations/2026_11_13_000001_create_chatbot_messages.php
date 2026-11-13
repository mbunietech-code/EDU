<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website chatbot (public pages): every visitor question and bot reply, keyed
 * by a random per-browser session key. Gives the bot short-term memory, lets
 * the daily caps count usage, and lets admins see what visitors ask.
 * Live DB: database/alters/0040_*.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 16);                 // user | assistant
            $table->text('content');
            $table->string('provider', 20)->nullable(); // deepseek | anthropic | openai
            $table->string('model', 100)->nullable();
            $table->string('status', 20)->nullable();   // ok | error | no_key | limit | disabled
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['session_key', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};
