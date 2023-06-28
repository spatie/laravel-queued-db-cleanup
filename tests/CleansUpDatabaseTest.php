<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelQueuedDbCleanup\CleanConfig;
use Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabaseCompleted;
use Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassStarting;
use Spatie\LaravelQueuedDbCleanup\Exceptions\CouldNotCreateJob;
use Spatie\LaravelQueuedDbCleanup\Exceptions\InvalidDatabaseCleanupJobClass;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\InvalidDatabaseCleanupJobClass as InvalidDatabaseCleanupJobTestClass;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\ValidDatabaseCleanupJobClass;

class CleansUpDatabaseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    /**
     * @test
     *
     * @dataProvider getTestCases
     */
    public function it_can_delete_records_in_the_right_amount_of_passes(
        int $totalRecords,
        int $chunkSize,
        int $remaining,
        int $passesPerformed
    ) {
        Event::fake();

        TestModel::factory()->count($totalRecords)->create();

        CleanDatabaseJobFactory::forQuery(TestModel::query())
            ->deleteChunkSize($chunkSize)
            ->dispatch();

        $this->assertSame($remaining, TestModel::count());

        Event::assertDispatched(function (CleanDatabaseCompleted $event) use ($totalRecords, $passesPerformed) {
            $this->assertSame($passesPerformed, $event->cleanConfig->pass);

            $this->assertSame($totalRecords, $event->cleanConfig->totalRowsDeleted);

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

    /** @test */
    public function it_can_continue_deleting_until_a_specified_condition()
    {
        TestModel::factory()->count(100)->create();

        CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->deleteChunkSize(10)
            ->stopWhen(function (CleanConfig $config) {
                return $config->pass === 3;
            })
            ->dispatch();

        $this->assertSame(70, TestModel::count());

        Event::assertDispatched(function (CleanDatabaseCompleted $event) {
            $this->assertSame(3, $event->cleanConfig->pass);

            $this->assertSame(10, $event->cleanConfig->rowsDeletedInThisPass);
            $this->assertSame(30, $event->cleanConfig->totalRowsDeleted);

            return true;
        });
    }

    /** @test */
    public function it_dispatches_a_start_event()
    {
        CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->deleteChunkSize(10)
            ->dispatch();

        Event::assertDispatched(function (CleanDatabasePassStarting $event) {
            $this->assertSame(1, $event->cleanConfig->pass);

            return true;
        });
    }

    /** @test */
    public function it_accepts_database_query_builder()
    {
        CleanDatabaseJobFactory::new()
            ->query(DB::table('test_models'))
            ->deleteChunkSize(10)
            ->dispatch();

        Event::assertDispatched(function (CleanDatabasePassStarting $event) {
            $this->assertSame(1, $event->cleanConfig->pass);

            return true;
        });
    }

    /** @test */
    public function it_will_not_clean_if_it_cannot_get_the_lock()
    {
        TestModel::factory()->count(10)->create();

        $jobFactory = CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->deleteChunkSize(10);

        $job = CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->deleteChunkSize(10)
            ->getJob();

        $job->config->lock()->get();
        $jobFactory->dispatch();
        $this->assertSame(10, TestModel::count());

        $job->config->lock()->forceRelease();
        $jobFactory->dispatch();
        $this->assertSame(0, TestModel::count());
    }

    /** @test */
    public function the_job_can_be_serialized()
    {
        $job = CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->deleteChunkSize(10)
            ->getJob();

        $this->assertIsString(serialize($job));
    }

    /** @test */
    public function it_respects_the_bindings()
    {
        TestModel::factory()->count(10)->create();

        CleanDatabaseJobFactory::new()
            ->query(TestModel::query()->where('id', 1))
            ->deleteChunkSize(10)
            ->dispatch();

        $this->assertSame(9, TestModel::count());
    }

    /** @test */
    public function it_can_use_a_custom_database_cleanup_job_class()
    {
        $job = CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->deleteChunkSize(10)
            ->jobClass(ValidDatabaseCleanupJobClass::class)
            ->getJob();

        $this->assertInstanceOf(ValidDatabaseCleanupJobClass::class, $job);
    }

    /** @test */
    public function it_throws_an_exception_if_an_invalid_job_class_is_used()
    {
        $this->expectException(InvalidDatabaseCleanupJobClass::class);

        CleanDatabaseJobFactory::new()->jobClass(InvalidDatabaseCleanupJobTestClass::class);
    }

    /** @test */
    public function it_throws_an_exception_if_no_query_was_set()
    {
        $this->expectException(CouldNotCreateJob::class);

        CleanDatabaseJobFactory::new()->dispatch();
    }

    /** @test */
    public function it_throws_an_exception_if_no_chunk_size_was_set()
    {
        $this->expectException(CouldNotCreateJob::class);

        CleanDatabaseJobFactory::new()->query(TestModel::query())->dispatch();
    }

    /** @test */
    public function it_can_use_a_custom_connection()
    {
        $config = CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->onDatabaseConnection('test')
            ->deleteChunkSize(10)
            ->cleanConfig;

        $this->assertEquals('test', $config->connection);
    }

    /** @test */
    public function it_can_delete_records_on_custom_connection()
    {
        TestModel::factory()->count(10)->create();
        TestModel::factory()->connection('sqliteSecondary')->count(10)->create();

        $this->assertDatabaseCount('test_models', 10, 'sqliteSecondary');
        $this->assertDatabaseCount('test_models', 10);

        CleanDatabaseJobFactory::new()
            ->query(TestModel::query())
            ->onDatabaseConnection('sqliteSecondary')
            ->deleteChunkSize(10)
            ->dispatch();

        $this->assertDatabaseCount('test_models', 0, 'sqliteSecondary');
        $this->assertDatabaseCount('test_models', 10);
    }
}
