<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Illuminate\Console\Command;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\GlobalCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassScanCache;
use Okaufmann\LaravelHorizonDoctor\Support\RedisQueueHorizonOverview;
use Symfony\Component\Console\Output\OutputInterface;

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
        private readonly QueuedClassScanRunner $queuedClassScanRunner,
        private readonly QueuedClassScanCache $queuedClassScanCache,
    ) {}

    public function run(Command $command): int
    {
        $failed = false;
        $hasWarnings = false;

        $this->queuedClassScanCache->reset();

        foreach ($this->globalChecks as $check) {
            foreach ($check->check() as $message) {
                $command->error("- {$message}");
                $failed = true;
            }
        }

        $queueConnections = config('queue.connections', []);
        if (! is_array($queueConnections)) {
            $queueConnections = [];
        }

        if ($this->shouldScanQueuedClasses($command)) {
            $scanResult = $this->queuedClassScanRunner->discoverAndRunRules(
                base_path(),
                $this->horizonDoctorScanConfig($command),
                $queueConnections
            );
            foreach ($scanResult->errors as $message) {
                $command->error($message);
                $failed = true;
            }
            foreach ($scanResult->warnings as $message) {
                $command->warn($message);
                $hasWarnings = true;
            }
        }

        $strictWarnings = $this->strictWarnings($command);

        $environments = config('horizon.environments');
        if (! is_array($environments) || $environments === []) {
            if ($hasWarnings && $strictWarnings) {
                $failed = true;
            }

            return $failed ? Command::FAILURE : Command::SUCCESS;
        }
        $verbose = $this->isVerbose($command);

        foreach (array_keys($environments) as $environment) {
            $this->printEnvironmentHeader($command, (string) $environment);

            $merged = $this->merger->mergeSupervisorsForEnvironment((string) $environment);

            $envHasErrors = false;
            $envHasWarnings = false;

            if ($this->shouldShowOverview($command)) {
                $this->renderRedisQueueOverview($command, (string) $environment, $merged, $queueConnections, $verbose);
            }

            [$envFailed, $envWarnings] = $this->runEnvironmentChecks(
                $command,
                $this->environmentChecksBeforeSupervisors,
                (string) $environment,
                $merged,
                $queueConnections,
                $verbose
            );
            $envHasErrors = $envHasErrors || $envFailed;
            $envHasWarnings = $envHasWarnings || $envWarnings;
            $failed = $envFailed || $failed;
            $hasWarnings = $envWarnings || $hasWarnings;

            foreach ($merged as $supervisorKey => $supervisorConfig) {
                if (! is_array($supervisorConfig)) {
                    continue;
                }

                $supervisorErrors = [];
                foreach ($this->supervisorChecks as $check) {
                    $supervisorErrors = array_merge(
                        $supervisorErrors,
                        $check->check((string) $environment, (string) $supervisorKey, $supervisorConfig, $queueConnections)
                    );
                }

                if ($supervisorErrors === []) {
                    if ($verbose) {
                        $command->info("Checking supervisor `{$supervisorKey}`");
                        $command->info('- Everything looks good!');
                        $command->comment('');
                    }
                } else {
                    $command->line("Supervisor <fg=yellow>{$supervisorKey}</>");
                    $envHasErrors = true;
                    $failed = true;
                    foreach ($supervisorErrors as $message) {
                        $command->error("  {$message}");
                    }
                    $command->newLine();
                }
            }

            if ($verbose) {
                $command->info('Running environment-level queue checks...');
                $command->comment("Here 'queue connection' means a Laravel key under config/queue.php → connections (same name as Horizon supervisor `connection`, #[Connection(...)], and dispatch()->onConnection(...)).");
                $command->comment('The nested key connections.{name}.connection points at a Redis client in config/database.php — not the same label; two queue connections can share one Redis client and the same Redis list queues:{queue}.');
                $command->comment('Queue names should line up with how you dispatch jobs. Warnings are consistency hints unless you pass --strict-warnings.');
            }

            [$envFailed, $envWarnings] = $this->runEnvironmentChecks(
                $command,
                $this->environmentChecksAfterSupervisors,
                (string) $environment,
                $merged,
                $queueConnections,
                $verbose
            );
            $envHasErrors = $envHasErrors || $envFailed;
            $envHasWarnings = $envHasWarnings || $envWarnings;
            $failed = $envFailed || $failed;
            $hasWarnings = $envWarnings || $hasWarnings;

            if ($verbose) {
                $command->comment('');
            }

            if (! $envHasErrors) {
                if ($envHasWarnings) {
                    $command->info('No errors found (see warnings above).');
                } else {
                    $command->info('No errors found.');
                }
                $command->newLine();
            }
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

    private function shouldScanQueuedClasses(Command $command): bool
    {
        if ($command->hasOption('no-scan-jobs') && (bool) $command->option('no-scan-jobs')) {
            return false;
        }

        if ($command->hasOption('scan-jobs') && (bool) $command->option('scan-jobs')) {
            return true;
        }

        $config = config('horizon-doctor.scan_queued_classes');
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

    private function isVerbose(Command $command): bool
    {
        if ($command->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            return true;
        }

        $config = config('horizon-doctor.verbose');
        if (is_bool($config)) {
            return $config;
        }

        return false;
    }

    private function printEnvironmentHeader(Command $command, string $environment): void
    {
        $bar = str_repeat('━', 52);
        $command->newLine();
        $command->line("<fg=cyan>{$bar}</>");
        $command->line("  <fg=cyan;options=bold>Environment: {$environment}</>");
        $command->line("<fg=cyan>{$bar}</>");
        $command->newLine();
    }

    /**
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    private function renderRedisQueueOverview(Command $command, string $environment, array $merged, array $queueConnections, bool $verbose): void
    {
        $rows = RedisQueueHorizonOverview::rows($merged, $queueConnections);
        $problemRows = array_values(array_filter($rows, fn (array $r) => ($r['status'] ?? '') !== 'OK'));
        $tableRows = $verbose ? $rows : $problemRows;

        if (! $verbose && $tableRows === []) {
            return;
        }

        if ($verbose) {
            $command->info("Redis queue overview (environment `{$environment}`)");
            $command->comment('Each row is one queue name on one Laravel queue connection (`config/queue.php` → `connections.{name}`). Column "Queue connection" is that Laravel name, not the Redis client (`connections.{name}.connection` → `config/database.php`).');
            $command->comment('Compared against supervisors in `config/horizon.php` → `environments.'.$environment.'` (supervisor `connection` must be the same queue connection name).');
        } else {
            $command->line('Queue / Horizon mismatches (use <fg=gray>-v</> for the full table and terminology notes)');
        }

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
            ['Queue', 'Queue connection', 'In queue.php', 'Horizon supervisors', 'Status'],
            RedisQueueHorizonOverview::toTableBody($tableRows)
        );

        if ($verbose) {
            $command->comment('Status: OK = queue name is listed under this queue connection in queue.php and a Horizon supervisor uses this same queue connection. Warning = Horizon runs the queue on this queue connection but queue.php does not list the name there. Error = queue.php lists it here but no supervisor uses this queue connection (or supervisors only use another queue connection name).');
        } elseif ($tableRows !== []) {
            $command->comment('"Queue connection" = Laravel name in config/queue.php → connections (not the Redis server client label).');
        }

        $command->newLine();
    }

    /**
     * @param  iterable<EnvironmentCheck>  $checks
     * @param  array<string, array<string, mixed>>  $merged
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return array{0: bool, 1: bool}
     */
    private function runEnvironmentChecks(Command $command, iterable $checks, string $environment, array $merged, array $queueConnections, bool $verbose): array
    {
        $result = EnvironmentCheckResult::ok();

        foreach ($checks as $check) {
            $result = $result->merge($check->check($environment, $merged, $queueConnections, $verbose));
        }

        if ($result->errors === [] && $result->warnings === []) {
            if ($verbose) {
                $command->info('- Everything looks good!');
            }

            return [false, false];
        }

        foreach ($result->errors as $message) {
            $command->error($message);
        }

        foreach ($result->warnings as $message) {
            $command->warn($message);
        }

        return [$result->errors !== [], $result->warnings !== []];
    }

    /**
     * Merged horizon-doctor options for the queued-class scan (config and CLI overrides).
     *
     * @return array<string, mixed>
     */
    private function horizonDoctorScanConfig(Command $command): array
    {
        $base = config('horizon-doctor', []);
        if (! is_array($base)) {
            $base = [];
        }

        if ($command->hasOption('no-strict-job-timeouts') && (bool) $command->option('no-strict-job-timeouts')) {
            $base['strict_job_timeouts'] = false;
        } elseif ($command->hasOption('strict-job-timeouts') && (bool) $command->option('strict-job-timeouts')) {
            $base['strict_job_timeouts'] = true;
        }

        return $base;
    }
}
