<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Illuminate\Console\Command;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\GlobalCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;
use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;

final class HorizonDoctorRunner
{
    /**
     * @param  iterable<GlobalCheck>  $globalChecks
     * @param  iterable<EnvironmentCheck>  $environmentChecksBeforeSupervisors
     * @param  iterable<SupervisorCheck>  $supervisorChecks
     * @param  iterable<EnvironmentCheck>  $environmentChecksAfterSupervisors
     */
    public function __construct(
        private readonly HorizonConfigMerger $merger,
        private readonly iterable $globalChecks,
        private readonly iterable $environmentChecksBeforeSupervisors,
        private readonly iterable $supervisorChecks,
        private readonly iterable $environmentChecksAfterSupervisors,
    ) {}

    public function run(Command $command): int
    {
        $failed = false;

        foreach ($this->globalChecks as $check) {
            foreach ($check->check() as $message) {
                $command->error("- {$message}");
                $failed = true;
            }
        }

        $environments = config('horizon.environments');
        if (! is_array($environments) || $environments === []) {
            return $failed ? Command::FAILURE : Command::SUCCESS;
        }

        $queueConnections = config('queue.connections', []);
        if (! is_array($queueConnections)) {
            $queueConnections = [];
        }

        foreach (array_keys($environments) as $environment) {
            $command->info("Checking environment `{$environment}`");

            $merged = $this->merger->mergeSupervisorsForEnvironment((string) $environment);

            $failed = $this->runEnvironmentChecks($command, $this->environmentChecksBeforeSupervisors, (string) $environment, $merged, $queueConnections) || $failed;

            foreach ($merged as $supervisorKey => $supervisorConfig) {
                if (! is_array($supervisorConfig)) {
                    continue;
                }

                $command->info("Checking supervisor `{$supervisorKey}`");

                $supervisorErrors = [];
                foreach ($this->supervisorChecks as $check) {
                    $supervisorErrors = array_merge(
                        $supervisorErrors,
                        $check->check((string) $environment, (string) $supervisorKey, $supervisorConfig, $queueConnections)
                    );
                }

                if ($supervisorErrors === []) {
                    $command->info('- Everything looks good!');
                } else {
                    $failed = true;
                    foreach ($supervisorErrors as $message) {
                        $command->error("- {$message}");
                    }
                }

                $command->comment('');
            }

            $command->info('Running environment-level queue checks...');
            $failed = $this->runEnvironmentChecks($command, $this->environmentChecksAfterSupervisors, (string) $environment, $merged, $queueConnections) || $failed;
            $command->comment('');
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param  iterable<EnvironmentCheck>  $checks
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    private function runEnvironmentChecks(Command $command, iterable $checks, string $environment, array $merged, array $queueConnections): bool
    {
        $messages = [];
        foreach ($checks as $check) {
            $messages = array_merge($messages, $check->check($environment, $merged, $queueConnections));
        }

        if ($messages === []) {
            $command->info('- Everything looks good!');

            return false;
        }

        foreach ($messages as $message) {
            $command->error("- {$message}");
        }

        return true;
    }
}
