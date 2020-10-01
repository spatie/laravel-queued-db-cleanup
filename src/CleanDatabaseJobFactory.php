<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Spatie\LaravelQueuedDbCleanup\Exceptions\CouldNotCreateJob;
use Spatie\LaravelQueuedDbCleanup\Exceptions\InvalidDatabaseCleanupJobClass;
use Spatie\LaravelQueuedDbCleanup\Jobs\CleanDatabaseJob;

class CleanDatabaseJobFactory
{
    public CleanConfig $cleanConfig;

    /** @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query */
    public $query;

    public ?int $deleteChunkSize = null;

    public string $jobClass;

    public static function new()
    {
        return new static();
    }

    public static function forQuery($query)
    {
        return new static($query);
    }

    /** @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query */
    public function __construct($query = null)
    {
        $this->jobClass = config('queued-db-cleanup.clean_database_job_class');

        $this->cleanConfig = new CleanConfig();

        $this->query = $query;
    }

    /** @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query */
    public function query($query): self
    {
        $this->query = $query;

        return $this;
    }

    public function deleteChunkSize(int $size): self
    {
        $this->deleteChunkSize = $size;

        return $this;
    }

    public function jobClass(string $databaseCleanupJobClass): self
    {
        if (! $this->isValidDatabaseCleanupJobClass($databaseCleanupJobClass)) {
            throw InvalidDatabaseCleanupJobClass::make($databaseCleanupJobClass);
        }

        $this->jobClass = $databaseCleanupJobClass;

        return $this;
    }

    public function displayName(string $displayName): self
    {
        $this->cleanConfig->displayName = $displayName;

        return $this;
    }

    public function tags(array $tags): self
    {
        $this->cleanConfig->tags = $tags;

        return $this;
    }

    public function onDatabaseConnection(string $connection): self
    {
        $this->cleanConfig->connection = $connection;

        return $this;
    }

    public function getJob(): CleanDatabaseJob
    {
        $this->ensureValid();

        $this->cleanConfig->usingQuery($this->query, $this->deleteChunkSize);

        return new $this->jobClass($this->cleanConfig);
    }

    public function getBatch(): PendingBatch
    {
        return Bus::batch([$this->getJob()]);
    }

    public function dispatch(): Batch
    {
        return $this->getBatch()->dispatch();
    }

    public function stopWhen(Closure $closure): self
    {
        $this->cleanConfig->stopWhen($closure);

        return $this;
    }

    protected function ensureValid(): void
    {
        if (is_null($this->query)) {
            throw CouldNotCreateJob::queryNotSet();
        }

        if (is_null($this->deleteChunkSize)) {
            throw CouldNotCreateJob::deleteChunkSizeNotSet();
        }
    }

    protected function isValidDatabaseCleanupJobClass(string $jobClass): bool
    {
        if ($jobClass === CleanDatabaseJob::class) {
            return true;
        }

        return is_subclass_of($jobClass, CleanDatabaseJob::class);
    }
}
