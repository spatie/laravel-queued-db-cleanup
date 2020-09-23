<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests;

use Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;

class CleansUpDatabaseTest extends TestCase
{
    /** @test */
    public function it_can_delete_records()
    {
        TestModel::factory()->count(1000)->create();

        CleanDatabaseJobFactory::new()
            ->usingQuery(TestModel::query())
            ->deleteChunkSize(100)
            ->dispatch();

        $this->assertEquals(0, TestModel::count());
    }
}
