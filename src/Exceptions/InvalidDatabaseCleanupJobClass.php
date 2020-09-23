<?php

namespace Spatie\LaravelQueuedDbCleanup\Exceptions;

use Exception;
use Spatie\LaravelQueuedDbCleanup\Jobs\CleanDatabaseJob;

class InvalidDatabaseCleanupJobClass extends Exception
{
    public static function make(string $invalidJobClass): self
    {
        return new static("`{$invalidJobClass}` is an invalid clean database job class. A valid class is any class that extends `" . CleanDatabaseJob::class . '`');
    }
}
