<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\SerializableClosure\SerializableClosure;

class CleanConfig
{
    public string $sql;

    public array $sqlBindings;

    public int $deleteChunkSize = 1000;

    public string $lockName = '';

    public int $pass = 1;

    public int $rowsDeletedInThisPass = 0;

    public int $totalRowsDeleted = 0;

    public ?string $stopWhen = null;

    public string $lockCacheStore;

    public array $tags = [];

    public ?string $displayName = null;

    public int $releaseLockAfterSeconds;

    public ?string $connection = null;

    public function __construct()
    {
        $this->lockCacheStore = config('queued-db-cleanup.lock.cache_store');

        $this->releaseLockAfterSeconds = config('queued-db-cleanup.lock.release_lock_after_seconds');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public function usingQuery($query, int $chunkSize)
    {
        $baseQuery = $query instanceof \Illuminate\Database\Eloquent\Builder
            ? $query->toBase()
            : $query;

        $this->sql = $query->limit($chunkSize)->getGrammar()->compileDelete($baseQuery);

        $this->sqlBindings = $query->getBindings();

        $this->deleteChunkSize = $chunkSize;

        $this->lockName = $this->convertQueryToLockName($query);

        if ($this->stopWhen === null) {
            $this->stopWhen(function (CleanConfig $cleanConfig) {
                return $cleanConfig->rowsDeletedInThisPass < $this->deleteChunkSize;
            });
        }
    }

    public function displayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function tags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function executeDeleteQuery(): int
    {
        return DB::connection($this->connection)->delete($this->sql, $this->sqlBindings);
    }

    public function stopWhen(Closure $closure)
    {
        $wrapper = new SerializableClosure($closure);

        $this->stopWhen = serialize($wrapper);
    }

    public function shouldContinueCleaning(): bool
    {
        /** @var SerializableClosure $wrapper */
        $wrapper = unserialize($this->stopWhen);

        $stopWhen = $wrapper->getClosure();

        return ! $stopWhen($this);
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

    public function lock(): Lock
    {
        return Cache::store($this->lockCacheStore)->lock($this->lockName, $this->releaseLockAfterSeconds);
    }

    /** @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder */
    protected function convertQueryToLockName($query): string
    {
        return md5($query->toSql().print_r($query->getBindings(), true));
    }
}
