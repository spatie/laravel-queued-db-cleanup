<?php

namespace Spatie\LaravelQueuedDbCleanup\Jobs\Middleware;

use Illuminate\Cache\Lock;

class AtomicJobMiddleware
{
    protected Lock $lock;

    public function __construct(Lock $lock)
    {
        $this->lock = $lock;
    }

    public function handle($job, $next)
    {
        if (! $this->lock->get()) {
            $job->delete();

            return;
        }
        dump('got lock');

        $next($job);

        dump('releasing lock');
        $this->lock->forceRelease();
    }
}
