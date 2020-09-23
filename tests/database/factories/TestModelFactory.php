<?php

namespace Spatie\LaravelQueuedDbCleanup\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\LaravelQueuedDbCleanup\Tests\TestClasses\TestModel;

class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    public function definition()
    {
        return [

        ];
    }
}
