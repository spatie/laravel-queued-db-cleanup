<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests;

use Spatie\LaravelQueuedDbCleanup\Commands\Concerns\CleansUpDatabase;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;

class CleansUpDatabaseTest extends TestCase
{
    use CleansUpDatabase;

    /** @test */
    public function true_is_true()
    {
        TestModel::factory()->count(100)->create();

        $this->assertEquals(100, TestModel::count());
    }
}
