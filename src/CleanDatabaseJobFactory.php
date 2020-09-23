<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;
use Illuminate\Foundation\Bus\PendingDispatch;
use Spatie\LaravelQueuedDbCleanup\Jobs\CleanUpDatabaseJob;

class CleanDatabaseJobFactory
{
    public CleanConfig $cleanConfig;

    /** @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query */
    public $query;

    public ?int $deleteChunkSize = null;

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
        $this->query = $query;

        return $this;
    }

    public function deleteChunkSize(int $size): self
    {
        $this->deleteChunkSize = $size;

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
        $this->cleanConfig->usingQuery($this->query, $this->deleteChunkSize);

        return dispatch($this->getJob());
    }

    public function stopWhen(Closure $closure): self
    {
        $this->cleanConfig->stopWhen($closure);

        return $this;
    }
}
