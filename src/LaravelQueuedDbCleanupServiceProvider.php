<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Illuminate\Support\ServiceProvider;
use Spatie\LaravelQueuedDbCleanup\Commands\LaravelQueuedDbCleanupCommand;

class LaravelQueuedDbCleanupServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laravel-queued-db-cleanup.php' => config_path('laravel-queued-db-cleanup.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/views' => base_path('resources/views/vendor/laravel-queued-db-cleanup'),
            ], 'views');

            $migrationFileName = 'create_laravel_queued_db_cleanup_table.php';
            if (! $this->migrationFileExists($migrationFileName)) {
                $this->publishes([
                    __DIR__ . "/../database/migrations/{$migrationFileName}.stub" => database_path('migrations/' . date('Y_m_d_His', time()) . '_' . $migrationFileName),
                ], 'migrations');
            }

            $this->commands([
                LaravelQueuedDbCleanupCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-queued-db-cleanup');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-queued-db-cleanup.php', 'laravel-queued-db-cleanup');
    }

    public static function migrationFileExists(string $migrationFileName): bool
    {
        $len = strlen($migrationFileName);
        foreach (glob(database_path("migrations/*.php")) as $filename) {
            if ((substr($filename, -$len) === $migrationFileName)) {
                return true;
            }
        }

        return false;
    }
}
