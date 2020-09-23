<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;
use Illuminate\Support\Facades\DB;
use Opis\Closure\SerializableClosure;

class CleanConfig
{
    public string $sql;

    public array $sqlBindings;

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

        $this->stopWhen = function (CleanConfig $cleanConfig) {
            return $cleanConfig->rowsDeletedInThisPass < $this->deleteChunkSize;
        };
    }

    /**
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param int $chunkSize
     */
    public function usingQuery($query, int $chunkSize)
    {
        $this->sql = $query->limit($chunkSize)->getGrammar()->compileDelete($query->toBase());

        $this->sqlBindings = $query->getBindings();

        $this->deleteChunkSize = $chunkSize;

        $this->lockName = $this->convertQueryToLockName($query);
    }

    public function executeDeleteQuery(): int
    {
        return DB::delete($this->sql, $this->sqlBindings);
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
