<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\LaravelQueuedDbCleanup\CleanConfig;
use Spatie\LaravelQueuedDbCleanup\Jobs\Middleware\AtomicJobMiddleware;

class CleanUpDatabaseJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public CleanConfig $config;

    public function __construct(CleanConfig $cleanConfig)
    {
        $this->config = $cleanConfig;
    }

    public function handle()
    {
        $numberOfRowsDeleted = $this->config->query->limit($this->config->limit)->delete();

        $this->config->rowsDeletedInThisPass($numberOfRowsDeleted);

        if ($this->config->shouldContinueCleaning()) {
            $this->redispatch();
        }
    }

    protected function redispatch()
    {
        $this->config->incrementPass();

        dispatch(new CleanUpDatabaseJob($this->config));
    }

    public function middleware()
    {
        return [new AtomicJobMiddleware($this->config->lockName)];
    }
}
