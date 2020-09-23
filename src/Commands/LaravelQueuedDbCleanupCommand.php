<?php

namespace Spatie\LaravelQueuedDbCleanup\Commands;

use Illuminate\Console\Command;

class LaravelQueuedDbCleanupCommand extends Command
{
    public $signature = 'laravel-queued-db-cleanup';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
