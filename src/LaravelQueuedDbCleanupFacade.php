<?php

namespace Spatie\LaravelQueuedDbCleanup;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Spatie\LaravelQueuedDbCleanup\LaravelQueuedDbCleanup
 */
class LaravelQueuedDbCleanupFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-queued-db-cleanup';
    }
}
