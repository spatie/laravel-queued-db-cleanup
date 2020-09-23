<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\LaravelQueuedDbCleanup\CleanupConfig;
use Spatie\LaravelQueuedDbCleanup\Jobs\Middleware\AtomicJobMiddleware;

class CleanUpDatabaseJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $query;

    public CleanupConfig $config;

    public string $name;

    public function __construct(string $name, $query, CleanupConfig $config)
    {
        $this->query = $query;

        $this->config = $config;

        $this->name = $name;
    }

    public function handle()
    {
        $numberOfRowsDeleted = $this->query->delete();

        if ($numberOfRowsDeleted === 0) {
            return;
        }

        if ($this->config->shouldContinue($numberOfRowsDeleted)) {
            $this->redispatch();
        }
    }

    protected function redispatch()
    {
        dispatch(new CleanUpDatabaseJob($this->name, $this->query, $this->config));
    }

    public function middleware()
    {
        return [new AtomicJobMiddleware($this->name)];
    }
}
