<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Illuminate\Support\ServiceProvider;

class LaravelQueuedDbCleanupServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/queued-db-cleanup.php' => config_path('queued-db-cleanup.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/queued-db-cleanup.php', 'queued-db-cleanup');
    }
}
