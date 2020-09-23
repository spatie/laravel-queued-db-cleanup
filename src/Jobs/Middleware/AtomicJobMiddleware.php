<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs\Middleware;

use Illuminate\Support\Facades\Cache;

class AtomicJobMiddleware
{
    protected string $lockName;

    public function __construct(string $lockName)
    {
        $this->lockName = $lockName;
    }

    public function handle($job, $next)
    {
        /** @var \Illuminate\Cache\RedisLock $lock */
        $lock = Cache::store('redis')->lock("{$this->lockName}_lock", 10 * 60);

        if (! $lock->get()) {
            $job->delete();

            return;
        }

        $next($job);

        $lock->release();
    }
}
