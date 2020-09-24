# Safely delete large numbers of records

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-queued-db-cleanup.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-queued-db-cleanup)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/spatie/laravel-queued-db-cleanup/run-tests?label=tests)](https://github.com/spatie/laravel-queued-db-cleanup/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-queued-db-cleanup.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-queued-db-cleanup)

Deleting many database records in one go using Laravel has a few pitfalls you need to be aware of:

- deleting records is possibly a slow operation that can take a long time,
- the delete query will acquire many row locks and possible lock your entire table, other queries will need to wait
- even when managing query execution and cleanup, there's a fixed maximum execution time in a serverless environment

The pitfalls described in more detail in [this post](https://flareapp.io/blog/7-how-to-safely-delete-records-in-massive-tables-on-aws-using-laravel) on the [Flare blog](https://flareapp.io/).

This package offers a solution to safely delete many records in large tables. Here's an example:

```php
Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory::new()
    ->query(YourModel::query()->where('created_at', '<',  now()->subMonth()))
    ->deleteChunkSize(1000)
    ->dispatch();
```

The code above will dispatch a cleanup job that will delete the 1000 first records that are selected for the query. When it detects that 1000 records have been deleted, it will conclude that possibly not all records are deleted and it will redispatch itself.

We'll also make sure that this cleanup job never overlaps. This way the number of database connections is kept low. It also allows you the schedule this cleanup job repeatedly throug CRON without having to check for an existing cleanup process.

By keeping the chunk size small the query executes faster, and potential table locks will not be held for long periods of time. The cleanup job will also finish fast, so you won't hit an execution time limit.

## Support us

Learn how to create a package like this one, by watching our premium video course:

[![Laravel Package training](https://spatie.be/github/package-training.jpg)](https://laravelpackage.training)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-queued-db-cleanup
```

The package uses a lock to prevent multiple deletions for the same query to be executed at the same time. We recommend using redis to store the lock.

Behind the scenes this package leverages [job batches](https://laravel.com/docs/master/queues#job-batching). Make sure your created the batches table mentioned in the Laravel documentation.

Optionally, you can publish the config file with:
```bash
php artisan vendor:publish --provider="Spatie\LaravelQueuedDbCleanup\LaravelQueuedDbCleanupServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [
    /*
     * To make sure there's only one job of a particular cleanup running,
     * this package uses a lock. Here, you can configure the default
     * store to be used by the lock and the release time.
     */
    'lock' => [
        'cache_store' => 'redis',

        'release_lock_after_seconds' => 60 * 20
    ],

    /*
     * The class name of the job that will clean that database.
     *
     * This should be `Spatie\LaravelQueuedDbCleanup\Jobs\CleanDatabaseJob`
     * or a class that extends it.
     */
    'clean_database_job_class' => Spatie\LaravelQueuedDbCleanup\Jobs\CleanDatabaseJob::class,
];
```

## Usage

This code above will dispatch a cleanup job that will delete the 1000 first records that are selected for the query. When it detects that 1000 records have been deleted, it will conclude that possibly not all records are deleted and it will redispatch itself.

```php
Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory::new()
    ->query(YourModel::query()->where('created_at', <,  now()->subMonth())
    ->deleteChunkSize(1000)
    ->dispatch();
```

The job will not redispatch itself when there were fewer records deleted than the number given to `deleteChunkSize`.

### Starting the cleanup in a scheduled tasks

It is safe to start the cleanup process from within a scheduled task. Internally the package will use a lock to make sure that no two cleanups using the same query are running at the same time.

If a scheduled task starts a cleanup process while another one is still running, the new cleanup process will be cancelled.

### Customizing the queue and connection name

Internally, the packages uses job batches. Using `getBatch` you can get the batch and call methods like `onConnection` and `onQueue` on it. Don't forget to dispatch the batch at the end, by calling `dispatch()`.

```php
Spatie\LaravelQueuedDbCleanup\CleanDatabaseJobFactory::new()
    ->query(YourModel::query()->where('created_at', <,  now()->subMonth())
    ->deleteChunkSize(1000)
    ->getBatch()
    ->onConnection('redis')
    ->onQueue('cleanups')
    ->dispatch()
```

### Manually stopping the cleanup process

By default, the cleanup jobs will not redispatch themselves anymore when they detect that they've deleted less records than the chunk size. You can customize this behaviour by calling `stopWhen`. It should receive a closure. If the closure returns `true` the cleanup will stop.

```php
CleanDatabaseJobFactory::forQuery(YourModel::query())
    ->deleteChunkSize(10)
    ->stopWhen(function (Spatie\LaravelQueuedDbCleanup\CleanConfig $config) {
        return $config->pass === 3;
    })
    ->dispatch();
```

`stopWhen` receives an instance of `Spatie\LaravelQueuedDbCleanup\CleanConfig`. It contains these properties to determine whether the cleanup should be stopped:

- `pass`: contains the number of times the cleanup job was started for this particular cleanup.
- `rowsDeletedInThisPass`: the number of rows deleted in this pass
- `totalRowsDeleted`: the total of number of rows deleted by in all passes.

### Using the batch to stop the cleanup process

You can use the batch id to stop the cleanup process

```php
$batch = CleanDatabaseJobFactory::forQuery(YourModel::query())
    ->deleteChunkSize(10)
    ->getBatch();

// you could store this batch id somewhere
$batchId = $batch->id;

$batch->dispatch()
```

Somewhere else in your codebase you could retrieve the stored batch id and use it to cancel the batch, stopping the cleanup process.

```php
\Illuminate\Support\Facades\Bus::findBatch($batchId)->cancel();
```

## Events

You can listen for these events. They all have one public property `cleanConfig` which is an instance of `Spatie\LaravelQueuedDbCleanup\CleanConfig`

### Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassStarting

Fired when a new pass starts in the cleanup process. 

### Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePassCompleted

Fired when a pass has been completed in the cleanup process. 

### Spatie\LaravelQueuedDbCleanup\Events\CleanDatabasePCompleted

Fired when the entire cleanup process has been completed. 


## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
