<?php

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

beforeEach(function () {
    Event::fake();
});

it('can delete records in the right amount of passes', function (
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

    expect(TestModel::count())->toBe($remaining);

    Event::assertDispatched(function (CleanDatabaseCompleted $event) use ($totalRecords, $passesPerformed) {
        expect($event->cleanConfig->pass)->toBe($passesPerformed)
            ->and($event->cleanConfig->totalRowsDeleted)->toBe($totalRecords);

        return true;
    });
})->with([
    [100, 10, 0, 11],
    [100, 10, 0, 11],
    [99, 10, 0, 10],
    [100, 5, 0, 21],
]);

it('can continue deleting until a specified condition', function () {
    TestModel::factory()->count(100)->create();

    CleanDatabaseJobFactory::new()
        ->query(TestModel::query())
        ->deleteChunkSize(10)
        ->stopWhen(function (CleanConfig $config) {
            return $config->pass === 3;
        })
        ->dispatch();

    expect(TestModel::count())->toBe(70);

    Event::assertDispatched(function (CleanDatabaseCompleted $event) {
        expect($event->cleanConfig->pass)->toBe(3)
            ->and($event->cleanConfig->rowsDeletedInThisPass)->toBe(10)
            ->and($event->cleanConfig->totalRowsDeleted)->toBe(30);

        return true;
    });
});

it('dispatches a start event', function () {
    CleanDatabaseJobFactory::new()
        ->query(TestModel::query())
        ->deleteChunkSize(10)
        ->dispatch();

    Event::assertDispatched(function (CleanDatabasePassStarting $event) {
        expect($event->cleanConfig->pass)->toBe(1);

        return true;
    });
});

it('will not clean if it cannot get the lock', function () {
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
    expect(TestModel::count())->toBe(10);

    $job->config->lock()->forceRelease();
    $jobFactory->dispatch();
    expect(TestModel::count())->toBe(0);
});

test('the job can be serialized', function () {
    $job = CleanDatabaseJobFactory::new()
        ->query(TestModel::query())
        ->deleteChunkSize(10)
        ->getJob();

    expect(serialize($job))->toBeString();
});

it('respects the bindings', function () {
    TestModel::factory()->count(10)->create();

    CleanDatabaseJobFactory::new()
        ->query(TestModel::query()->where('id', 1))
        ->deleteChunkSize(10)
        ->dispatch();

    expect(TestModel::count())->toBe(9);
});

it('can use a custom database cleanup job class', function () {
    $job = CleanDatabaseJobFactory::new()
        ->query(TestModel::query())
        ->deleteChunkSize(10)
        ->jobClass(ValidDatabaseCleanupJobClass::class)
        ->getJob();

    expect($job)->toBeInstanceOf(ValidDatabaseCleanupJobClass::class);
});

it('throws an exception if an invalid job class is used')
    ->tap(fn () => CleanDatabaseJobFactory::new()->jobClass(InvalidDatabaseCleanupJobTestClass::class))
    ->throws(InvalidDatabaseCleanupJobClass::class);

it('throws an exception if no query was set')
    ->tap(fn () => CleanDatabaseJobFactory::new()->dispatch())
    ->throws(CouldNotCreateJob::class);

it('throws an exception if no chunk size was set')
    ->tap(fn () => CleanDatabaseJobFactory::new()->query(TestModel::query())->dispatch())
    ->throws(CouldNotCreateJob::class);

it('can use a custom connection', function () {
    $config = CleanDatabaseJobFactory::new()
        ->query(TestModel::query())
        ->onDatabaseConnection('test')
        ->deleteChunkSize(10)
        ->cleanConfig;

    expect($config->connection)->toEqual('test');
});

it('can delete records on custom connection', function () {
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
});
