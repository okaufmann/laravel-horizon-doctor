<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\HorizonSupervisorsDefinedCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Environment\RedisConnectionQueuesCoveredByHorizonCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Environment\RedisConnectionsUsedInHorizonCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Global\HorizonEnvironmentsConfiguredCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\QueueConnectionExistsForSupervisorCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorMaxProcessesPositiveCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorTimeoutLessThanQueueRetryAfterCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorTimeoutOptionPresentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorUsesRedisQueueDriverCheck;
use Okaufmann\LaravelHorizonDoctor\Commands\LaravelHorizonDoctorCommand;
use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;
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

    public function registeringPackage(): void
    {
        $this->app->singleton(HorizonConfigMerger::class);

        $this->app->singleton(HorizonDoctorRunner::class, function ($app) {
            return new HorizonDoctorRunner(
                $app->make(HorizonConfigMerger::class),
                [
                    new HorizonEnvironmentsConfiguredCheck(),
                ],
                [
                    new HorizonSupervisorsDefinedCheck(),
                ],
                [
                    new QueueConnectionExistsForSupervisorCheck(),
                    new SupervisorUsesRedisQueueDriverCheck(),
                    new SupervisorTimeoutOptionPresentCheck(),
                    new SupervisorTimeoutLessThanQueueRetryAfterCheck(),
                    new SupervisorMaxProcessesPositiveCheck(),
                ],
                [
                    new RedisConnectionQueuesCoveredByHorizonCheck(),
                    new RedisConnectionsUsedInHorizonCheck(),
                ],
            );
        });
    }
}
