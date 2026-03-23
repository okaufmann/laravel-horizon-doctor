<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Illuminate\Console\Command;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\GlobalCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;
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
                $command->comment('Redis connections in config/queue.php are compared to Horizon supervisors in config/horizon.php; queue names should line up with how you dispatch jobs. Warnings are consistency hints unless you pass --strict-warnings.');
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
        $fromEnv = getenv('HORIZON_DOCTOR_VERBOSE');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return filter_var($fromEnv, FILTER_VALIDATE_BOOLEAN);
        }

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
            $command->comment('Rows are queue names on each Redis connection: `config/queue.php` → `connections.{name}.queue` vs supervisors under `config/horizon.php` → `environments.'.$environment.'`.');
        } else {
            $command->line('Queue / Horizon mismatches (use <fg=gray>-v</> for the full table)');
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
            ['Queue', 'Connection', 'In queue.php', 'Horizon supervisors', 'Status'],
            RedisQueueHorizonOverview::toTableBody($tableRows)
        );

        if ($verbose) {
            $command->comment('Status: OK = listed under this connection in queue.php and a Horizon supervisor runs it here. Warning = Horizon runs it here but the name is not listed under this connection. Error = listed here but no supervisor on this connection (or Horizon only uses another connection).');
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
}
