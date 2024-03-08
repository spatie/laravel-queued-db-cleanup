<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelQueuedDbCleanup\CleanConfig;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabaseCompleted;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassCompleted;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassStarting;

class CleanDatabaseJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public CleanConfig $config;

    public function __construct(CleanConfig $config)
    {
        $this->config = $config;
    }

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        if (! $this->config->lock()->get()) {
            return;
        }

        $numberOfRowsDeleted = $this->performCleaning();

        $this->config->lock()->forceRelease();

        $this->config->rowsDeletedInThisPass($numberOfRowsDeleted);

        $this->config->shouldContinueCleaning()
            ? $this->continueCleaning()
            : $this->finishCleanup();
    }

    public function tags()
    {
        return $this->config->tags;
    }

    public function displayName()
    {
        return $this->config->displayName ?? static::class;
    }

    protected function performCleaning(): int
    {
        event(new CleanDatabasePassStarting($this->config));

        return DB::transaction(function () {
            return $this->config->executeDeleteQuery();
        }, config('queued-db-cleanup.delete_query_attempts'));
    }

    protected function continueCleaning(): void
    {
        event(new CleanDatabasePassCompleted($this->config));

        $this->config->incrementPass();

        $this->batch()->add([new CleanDatabaseJob($this->config)]);
    }

    protected function finishCleanup(): void
    {
        event(new CleanDatabasePassCompleted($this->config));

        event(new CleanDatabaseCompleted($this->config));
    }
}
