<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabaseCompleted;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;

class CleansUpDatabaseTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider getTestCases
     */
    public function it_can_delete_records_in_the_right_amount_of_passes(
        int $totalRecords,
        int $chunkSize,
        int $remaining,
        int $passesPerformed)
    {
        Event::fake();

        TestModel::factory()->count($totalRecords)->create();

        CleanDatabaseJobFactory::new()
            ->usingQuery(TestModel::query())
            ->deleteChunkSize($chunkSize)
            ->dispatch();

        $this->assertEquals($remaining, TestModel::count());

        Event::assertDispatched(function (CleanDatabaseCompleted $event) use ($totalRecords, $passesPerformed) {
            $this->assertEquals($passesPerformed, $event->cleanConfig->pass);

            $this->assertEquals($totalRecords, $event->cleanConfig->totalRowsDeleted);

            return true;
        });
    }

    public function getTestCases(): array
    {
        return [
            [100, 10, 0, 11],
            [100, 10, 0, 11],
            [99, 10, 0, 10],
            [100, 5, 0, 21],
        ];
    }

    public function it_can_use_a_custom_bla()
    {
        CleanDatabaseJobFactory::new()
            ->usingQuery(TestModel::query())
            ->deleteChunkSize(100)
            ->continueUntilNoneRemaining()
            ->dispatch();
    }
}
