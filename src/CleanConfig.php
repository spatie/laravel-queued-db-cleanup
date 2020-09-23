<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;

class CleanConfig
{
    /** @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder */
    public $query;

    public int $deleteChunkSize = 1000;

    public string $lockName = '';

    public int $pass = 1;

    public int $rowsDeletedInThisPass = 0;

    public int $totalRowsDeleted = 0;

    public Closure $stopWhen;

    public string $lockCacheStore;

    public int $releaseLockAfterSeconds;

    public function __construct()
    {
        $this->lockCacheStore = config('queued-db-cleanup.lock.release_lock_after_seconds');

        $this->releaseLockAfterSeconds = config('queued-db-cleanup.lock.release_lock_after_seconds');
    }

    /** @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query */
    public function usingQuery($query)
    {
        $this->query = $query;

        $this->stopWhen = function (CleanConfig $cleanConfig) {
            return $cleanConfig->rowsDeletedInThisPass < $this->deleteChunkSize;
        };

        $this->lockName = $this->convertQueryToLockName($this->query);
    }

    public function deleteChunkSize(int $deleteChunkSize)
    {
        $this->deleteChunkSize = $deleteChunkSize;

        return $this;
    }

    public function stopWhen(callable $callable)
    {
        $this->stopWhen = $callable;
    }

    public function shouldContinueCleaning(): bool
    {
        return ! ($this->stopWhen)($this);
    }

    public function rowsDeletedInThisPass(int $rowsDeleted): self
    {
        $this->rowsDeletedInThisPass = $rowsDeleted;

        $this->totalRowsDeleted += $rowsDeleted;

        return $this;
    }

    public function incrementPass(): self
    {
        $this->pass++;

        $this->rowsDeletedInThisPass = 0;

        return $this;
    }

    /** @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder */
    protected function convertQueryToLockName($query): string
    {
        return md5($query->toSql() . print_r($query->getBindings(), true));
    }
}
