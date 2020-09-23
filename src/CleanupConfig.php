<?php

namespace Spatie\LaravelQueuedDbCleanup;

class CleanupConfig
{
    public function shouldContinue(int $numberOfRowsDeleted): bool
    {
        return true;
    }
}
