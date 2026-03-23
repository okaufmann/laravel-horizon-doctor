<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\QueueConfigNormalizer;

/**
 * Warns when Horizon supervises queues on a Redis connection but those names are not listed under
 * the same connection in config/queue.php — dispatch defaults and docs can then disagree with Horizon.
 */
final class HorizonQueuesDocumentedInQueuePhpCheck implements EnvironmentCheck
{
    private const NOTE = 'This is a consistency check: `config/queue.php` documents the default queue name(s) for each connection (and is the fallback when a supervisor’s `queue` in `config/horizon.php` is empty). Horizon still runs the supervisor `queue` list when it is non-empty.';

    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections, bool $verbose = false): EnvironmentCheckResult
    {
        $redisConnections = Collection::make($queueConnections)
            ->filter(fn ($config) => is_array($config) && ($config['driver'] ?? null) === 'redis');

        /** @var array<string, array<string, list<string>>> $horizonQueuesToSupervisors */
        $horizonQueuesToSupervisors = [];

        foreach ($mergedHorizonSupervisors as $supervisorKey => $supervisorConfig) {
            if (! is_array($supervisorConfig)) {
                continue;
            }

            $connection = $supervisorConfig['connection'] ?? null;
            if (! is_string($connection) || $connection === '' || ! $redisConnections->has($connection)) {
                continue;
            }

            $queues = QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisorConfig, $queueConnections);
            foreach ($queues as $queue) {
                $horizonQueuesToSupervisors[$connection][$queue][] = (string) $supervisorKey;
            }
        }

        $warnings = [];

        foreach ($horizonQueuesToSupervisors as $connectionName => $byQueue) {
            $config = $queueConnections[$connectionName] ?? null;
            if (! is_array($config)) {
                continue;
            }

            $documented = QueueConfigNormalizer::listedQueueNames($config);
            $documentedSet = array_flip($documented);

            $missing = [];
            foreach (array_keys($byQueue) as $queue) {
                if (! isset($documentedSet[$queue])) {
                    $missing[$queue] = array_values(array_unique($byQueue[$queue]));
                }
            }

            if ($missing === []) {
                continue;
            }

            ksort($missing, SORT_STRING);
            $warnings[] = $this->formatWarning($environment, $connectionName, $missing, $documented, $verbose);
        }

        return EnvironmentCheckResult::warnings($warnings);
    }

    /**
     * @param  array<string, list<string>>  $missingQueueToSupervisors
     * @param  list<string>  $documentedQueues
     */
    private function formatWarning(
        string $environment,
        string $connectionName,
        array $missingQueueToSupervisors,
        array $documentedQueues,
        bool $verbose
    ): string {
        $queueKey = "connections.{$connectionName}.queue";
        $documentedHuman = $this->formatDocumentedList($documentedQueues);

        $parts = [];
        foreach ($missingQueueToSupervisors as $queue => $supervisors) {
            sort($supervisors, SORT_STRING);
            $supHuman = $this->formatSupervisorList($supervisors);
            $parts[] = "`{$queue}` ({$supHuman})";
        }

        $missingHuman = implode('; ', $parts);

        $short = "`{$connectionName}`: Horizon runs {$missingHuman} but `{$queueKey}` does not list ".(count($missingQueueToSupervisors) === 1 ? 'that queue' : 'those queues')." (documented: {$documentedHuman}). Add the names to `queue.php` or change supervisor `queue` / `connection` in `environments.{$environment}`.";

        return $verbose ? $short.' '.self::NOTE : $short;
    }

    /**
     * @param  list<string>  $documentedQueues
     */
    private function formatDocumentedList(array $documentedQueues): string
    {
        if ($documentedQueues === []) {
            return 'no queue names (empty `queue` array)';
        }

        if (count($documentedQueues) === 1) {
            return "`{$documentedQueues[0]}` only";
        }

        return $this->formatQueueList($documentedQueues);
    }

    /**
     * @param  list<string>  $queues
     */
    private function formatQueueList(array $queues): string
    {
        return implode(', ', array_map(fn (string $q) => "`{$q}`", $queues));
    }

    /**
     * @param  list<string>  $supervisors
     */
    private function formatSupervisorList(array $supervisors): string
    {
        if (count($supervisors) === 1) {
            return "supervisor `{$supervisors[0]}`";
        }

        $parts = array_map(fn (string $s) => "`{$s}`", $supervisors);

        return 'supervisors '.implode(', ', $parts);
    }
}
