<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabaseCompleted;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;

class CleansUpDatabaseTest extends TestCase
{
    /** @test */
    public function it_can_delete_records()
    {
        Event::fake();

        TestModel::factory()->count(1000)->create();

        CleanDatabaseJobFactory::new()
            ->usingQuery(TestModel::query())
            ->deleteChunkSize(100)
            ->dispatch();

        $this->assertEquals(0, TestModel::count());

        Event::assertDispatched(function (CleanDatabaseCompleted $event) {
            $this->assertEquals(11, $event->cleanConfig->pass);

            return true;
        });
    }
}
