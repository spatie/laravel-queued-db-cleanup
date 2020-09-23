<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Illuminate\Support\ServiceProvider;

class LaravelQueuedDbCleanupServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laravel-queued-db-cleanup.php' => config_path('laravel-queued-db-cleanup.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-queued-db-cleanup.php', 'laravel-queued-db-cleanup');
    }
}
