<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Illuminate\Console\Command;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\GlobalCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;
use Okaufmann\LaravelHorizonDoctor\Support\RedisQueueHorizonOverview;

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
        $hasWarnings = false;

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

        $strictWarnings = $this->strictWarnings($command);

        foreach (array_keys($environments) as $environment) {
            $command->info("Checking environment `{$environment}`");

            $merged = $this->merger->mergeSupervisorsForEnvironment((string) $environment);

            if ($this->shouldShowOverview($command)) {
                $this->renderRedisQueueOverview($command, (string) $environment, $merged, $queueConnections);
            }

            [$envFailed, $envWarnings] = $this->runEnvironmentChecks(
                $command,
                $this->environmentChecksBeforeSupervisors,
                (string) $environment,
                $merged,
                $queueConnections
            );
            $failed = $envFailed || $failed;
            $hasWarnings = $envWarnings || $hasWarnings;

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
            $command->comment('Redis connections in config/queue.php are compared to Horizon supervisors in config/horizon.php; queue names should line up with how you dispatch jobs. Warnings are consistency hints unless you pass --strict-warnings.');
            [$envFailed, $envWarnings] = $this->runEnvironmentChecks(
                $command,
                $this->environmentChecksAfterSupervisors,
                (string) $environment,
                $merged,
                $queueConnections
            );
            $failed = $envFailed || $failed;
            $hasWarnings = $envWarnings || $hasWarnings;
            $command->comment('');
        }

        if ($hasWarnings && $strictWarnings) {
            $failed = true;
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function strictWarnings(Command $command): bool
    {
        if ($command->hasOption('strict-warnings') && (bool) $command->option('strict-warnings')) {
            return true;
        }

        $config = config('horizon-doctor.strict_warnings');
        if (is_bool($config)) {
            return $config;
        }

        return false;
    }

    private function shouldShowOverview(Command $command): bool
    {
        if ($command->hasOption('no-overview') && (bool) $command->option('no-overview')) {
            return false;
        }

        $config = config('horizon-doctor.show_overview');
        if (is_bool($config)) {
            return $config;
        }

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    private function renderRedisQueueOverview(Command $command, string $environment, array $merged, array $queueConnections): void
    {
        $rows = RedisQueueHorizonOverview::rows($merged, $queueConnections);

        $command->info("Redis queue overview (environment `{$environment}`)");
        $command->comment('Rows are queue names on each Redis connection: `config/queue.php` → `connections.{name}.queue` vs supervisors under `config/horizon.php` → `environments.'.$environment.'`.');

        if ($rows === []) {
            $hasRedis = collect($queueConnections)
                ->filter(fn ($c) => is_array($c) && ($c['driver'] ?? null) === 'redis')
                ->isNotEmpty();

            if (! $hasRedis) {
                $command->comment('No Redis queue connections in `config/queue.php` — nothing to map.');
            } else {
                $command->comment('No rows (no listed queues on Redis connections and no Horizon workers on Redis).');
            }
            $command->newLine();

            return;
        }

        $command->table(
            ['Queue', 'Connection', 'In queue.php', 'Horizon supervisors', 'Status'],
            RedisQueueHorizonOverview::toTableBody($rows)
        );
        $command->comment('Status: OK = listed under this connection in queue.php and a Horizon supervisor runs it here. Warning = Horizon runs it here but the name is not listed under this connection. Error = listed here but no supervisor on this connection (or Horizon only uses another connection).');
        $command->newLine();
    }

    /**
     * @param  iterable<EnvironmentCheck>  $checks
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return array{0: bool, 1: bool}
     */
    private function runEnvironmentChecks(Command $command, iterable $checks, string $environment, array $merged, array $queueConnections): array
    {
        $result = EnvironmentCheckResult::ok();

        foreach ($checks as $check) {
            $result = $result->merge($check->check($environment, $merged, $queueConnections));
        }

        if ($result->errors === [] && $result->warnings === []) {
            $command->info('- Everything looks good!');

            return [false, false];
        }

        foreach ($result->errors as $message) {
            $command->error("- {$message}");
        }

        foreach ($result->warnings as $message) {
            $command->warn("- {$message}");
        }

        return [$result->errors !== [], $result->warnings !== []];
    }
}
