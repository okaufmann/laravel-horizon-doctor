<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Okaufmann\LaravelHorizonDoctor\Commands\LaravelHorizonDoctorCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelHorizonDoctorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-horizon-doctor')
            ->hasConfigFile()
            ->hasCommand(LaravelHorizonDoctorCommand::class);
    }
}
