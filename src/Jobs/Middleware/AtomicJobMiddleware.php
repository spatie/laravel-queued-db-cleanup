<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs\Middleware;

use Illuminate\Support\Facades\Cache;
use Spatie\LaravelQueuedDbCleanup\CleanConfig;

class AtomicJobMiddleware
{
    protected CleanConfig $cleanConfig;

    public function __construct(CleanConfig $cleanConfig)
    {
        $this->cleanConfig = $cleanConfig;
    }

    public function handle($job, $next)
    {
        /** @var \Illuminate\Cache\RedisLock $lock */
        $lock = Cache::store($this->cleanConfig->lockCacheStore)
            ->lock("{$this->cleanConfig->lockName}_lock", $this->cleanConfig->releaseLockAfterSeconds);

        if (! $lock->get()) {
            $job->delete();

            return;
        }

        $next($job);

        $lock->release();
    }
}
