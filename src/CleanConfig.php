<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;

class CleanConfig
{
    /** @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder */
    public $query;

    public int $limit = 1000;

    public string $lockName = '';

    public int $pass = 1;

    public int $rowsDeletedInThisPass = 0;

    public int $totalRowsDeleted = 0;

    public Closure $stopCleaningWhen;

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

        $this->stopCleaningWhen = function (CleanConfig $cleanConfig) {
            return $cleanConfig->rowsDeletedInThisPass < $this->limit;
        };

        $this->lockName = $this->convertQueryToLockName($this->query);
    }

    public function limit(int $limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function stopCleaningWhen(callable $callable)
    {
        $this->stopCleaningWhen = $callable;
    }

    public function shouldContinueCleaning(): bool
    {
        return ! ($this->stopCleaningWhen)($this);
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
