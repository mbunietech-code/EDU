<?php

namespace App\Providers;

use App\Listeners\PushDatabaseNotification;
use App\Services\MailSettingsService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (Schema::hasTable('settings')) {
                $this->app->make(MailSettingsService::class)->apply();
            }
        } catch (\Throwable $e) {
            // Database not reachable/migrated yet (e.g. during initial setup) — skip.
        }

        Event::listen(NotificationSent::class, PushDatabaseNotification::class);
    }
}
