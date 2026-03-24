<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\HorizonQueuesDocumentedInQueuePhpCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Environment\HorizonSupervisorsDefinedCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Environment\RedisConnectionQueuesCoveredByHorizonCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Environment\RedisConnectionsUsedInHorizonCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Global\HorizonEnvironmentsConfiguredCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedClassHorizonQueueCoverageCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedJobTimeoutVsRetryAfterRule;
use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedListenerConstructorOnQueueRule;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\QueueConnectionExistsForSupervisorCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorMaxProcessesPositiveCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorTimeoutLessThanQueueRetryAfterCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorTimeoutOptionPresentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorUsesRedisQueueDriverCheck;
use Okaufmann\LaravelHorizonDoctor\Commands\LaravelHorizonDoctorCommand;
use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassAstAnalyzer;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassDiscovery;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassScanCache;
use PhpParser\Parser;
use PhpParser\ParserFactory;
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

        $this->app->singleton(Parser::class, static fn () => (new ParserFactory())->createForNewestSupportedVersion());

        $this->app->singleton(QueuedClassScanCache::class);

        $this->app->singleton(QueuedClassDiscovery::class);

        $this->app->singleton(QueuedClassAstAnalyzer::class);

        $this->app->singleton(QueuedJobTimeoutVsRetryAfterRule::class);

        $this->app->singleton(QueuedListenerConstructorOnQueueRule::class);

        $this->app->singleton(QueuedClassScanRunner::class);

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
                    new HorizonQueuesDocumentedInQueuePhpCheck(),
                    new RedisConnectionsUsedInHorizonCheck(),
                    new QueuedClassHorizonQueueCoverageCheck(
                        $app->make(QueuedClassScanCache::class),
                    ),
                ],
                $app->make(QueuedClassScanRunner::class),
                $app->make(QueuedClassScanCache::class),
            );
        });
    }
}
