<?php

namespace Spatie\LaravelQueuedDbCleanup\Events;

use Spatie\LaravelQueuedDbCleanup\CleanConfig;

class CleanDatabasePassCompleted
{
    public CleanConfig $cleanConfig;

    public function __construct(CleanConfig $cleanConfig)
    {
        $this->cleanConfig = $cleanConfig;
    }
}
