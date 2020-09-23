<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\LaravelQueuedDbCleanup\CleanConfig;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabaseCompleted;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassCompleted;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassStarting;

class CleanUpDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CleanConfig $config;

    public function __construct(CleanConfig $cleanConfig)
    {
        $this->config = $cleanConfig;
    }

    public function handle()
    {
        if (! $this->config->lock()->get()) {
            return;
        }

        event(new CleanDatabasePassStarting($this->config));

        $numberOfRowsDeleted = $this->config->executeDeleteQuery();

        $this->config->lock()->forceRelease();

        $this->config->rowsDeletedInThisPass($numberOfRowsDeleted);

        if ($this->config->shouldContinueCleaning()) {
            $this->redispatch();

            return;
        }

        event(new CleanDatabasePassCompleted($this->config));
        event(new CleanDatabaseCompleted($this->config));
    }

    protected function redispatch()
    {
        event(new CleanDatabasePassCompleted($this->config));

        $this->config->incrementPass();

        dispatch(new CleanUpDatabaseJob($this->config));
    }
}
