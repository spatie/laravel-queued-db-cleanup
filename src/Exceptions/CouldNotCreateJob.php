<?php

namespace Spatie\LaravelQueuedDbCleanup\Exceptions;

use Exception;

class CouldNotCreateJob extends Exception
{
    public static function queryNotSet(): self
    {
        return new static('Could not create job because no query was set');
    }

    public static function deleteChunkSizeNotSet(): self
    {
        return new static('Could not create job because delete chunk size was not set');
    }
}
