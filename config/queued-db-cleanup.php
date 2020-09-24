<?php

return [
    /*
     * To make sure there's only one job of a particular cleanup running,
     * this package uses a lock. Here, you can configure the default
     * store to be used by the lock and the release time.
     */
    'lock' => [
        'cache_store' => 'redis',

        'release_lock_after_seconds' => 60 * 20
    ],

    /*
     * The class name of the job that will clean that database.
     *
     * This should be `Spatie\LaravelQueuedDbCleanup\Jobs\CleanDatabaseJob`
     * or a class that extends it.
     */
    'clean_database_job_class' => Spatie\LaravelQueuedDbCleanup\Jobs\CleanDatabaseJob::class,
];
