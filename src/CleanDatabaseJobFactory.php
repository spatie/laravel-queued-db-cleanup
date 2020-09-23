<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;
use Illuminate\Foundation\Bus\PendingDispatch;
use Spatie\LaravelQueuedDbCleanup\Jobs\CleanUpDatabaseJob;

class CleanDatabaseJobFactory
{
    public CleanConfig $cleanConfig;

    public static function new()
    {
        return new static();
    }

    public function __construct()
    {
        $this->cleanConfig = new CleanConfig();
    }

    /** @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query */
    public function usingQuery($query): self
    {
        $this->cleanConfig->usingQuery($query);

        return $this;
    }

    public function deleteChunkSize(int $size): self
    {
        $this->cleanConfig->deleteChunkSize($size);

        return $this;
    }

    public function shouldContinue(int $numberOfRowsDeleted): bool
    {
        return true;
    }

    public function getJob(): CleanUpDatabaseJob
    {
        return new CleanUpDatabaseJob($this->cleanConfig);
    }

    public function dispatch(): PendingDispatch
    {
        return dispatch($this->getJob());
    }

    public function stopWhen(Closure $closure): self
    {
        $this->cleanConfig->stopCleaningWhen($closure);

        return $this;
    }
}
