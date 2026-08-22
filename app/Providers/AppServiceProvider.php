<?php

namespace App\Providers;

use App\Services\MailSettingsService;
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
    }
}
