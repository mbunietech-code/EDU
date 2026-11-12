<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site and app analytics for Admin -> Reports -> Analytics: page views on the
 * website, app downloads and app opens, each with a hashed device id so the
 * report can tell one device from many. Live DB: database/alters/0039_*.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                 // page_view | download | app_open
            $table->string('visitor', 64);              // hashed device id (never the raw cookie or IP)
            $table->boolean('new_visitor')->default(false); // first event ever from this device
            $table->string('platform', 20)->nullable();  // app platform (download / app_open)
            $table->string('source', 20)->nullable();    // download: web | update
            $table->string('app_version', 20)->nullable();
            $table->string('path', 255)->nullable();     // page_view
            $table->string('referrer', 120)->nullable(); // referring host
            $table->string('device', 10)->nullable();    // mobile | tablet | desktop | app
            $table->string('os', 20)->nullable();
            $table->string('browser', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index(['type', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
