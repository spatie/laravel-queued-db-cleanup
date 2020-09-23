<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests;

use Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;

class CleansUpDatabaseTest extends TestCase
{
    /** @test */
    public function it_can_delete_records()
    {
        TestModel::factory()->count(100)->create();

        $this->assertEquals(100, TestModel::count());

        CleanDatabaseJobFactory::new()
            ->usingQuery(TestModel::query())
            ->deleteChunkSize(1000)
            ->dispatch();
    }
}
