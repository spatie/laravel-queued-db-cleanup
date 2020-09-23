<?php

namespace Spatie\LaravelQueuedDbCleanup\Commands\Concerns;

use Illuminate\Database\Query\Builder;
use Spatie\LaravelQueuedDbCleanup\CleanupConfig;
use Spatie\LaravelQueuedDbCleanup\Jobs\CleanUpDatabaseJob;

trait CleansUpDatabase
{
    public function executeCleanUpQuery(string $name, Builder $query, CleanupConfig $cleanupConfig)
    {
        $job = new CleanUpDatabaseJob($name, $query, $cleanupConfig);

        dispatch($job);
    }
}
