<?php

namespace Okaufmann\LaravelHorizonDoctor\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Okaufmann\LaravelHorizonDoctor\LaravelHorizonDoctorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Okaufmann\\LaravelHorizonDoctor\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelHorizonDoctorServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-horizon-doctor_table.php.stub';
        $migration->up();
        */
    }
}
