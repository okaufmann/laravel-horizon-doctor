<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\QueueConfigNormalizer;

final class RedisConnectionQueuesCoveredByHorizonCheck implements EnvironmentCheck
{
    private const HORIZON_QUEUE_NOTE = 'Horizon uses each supervisor’s non-empty `queue` in `config/horizon.php`; if that list is empty, workers fall back to `connections.*.queue` in `config/queue.php` (default queue name / `RedisQueue` when no queue is passed).';

    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): EnvironmentCheckResult
    {
        $processedQueuesByConnection = $this->queuesHandledPerConnection($mergedHorizonSupervisors, $queueConnections);
        $placementsByQueue = $this->horizonPlacementsByQueueName($mergedHorizonSupervisors, $queueConnections);

        $redisConnections = Collection::make($queueConnections)
            ->filter(fn ($config) => is_array($config) && ($config['driver'] ?? null) === 'redis');

        $withHint = [];
        $withoutHint = [];

        foreach ($redisConnections as $connectionName => $queueConfig) {
            if (! is_array($queueConfig)) {
                continue;
            }

            $handledQueues = Collection::make(QueueConfigNormalizer::listedQueueNames($queueConfig));
            $horizonQueuesForConnection = $processedQueuesByConnection->get($connectionName, collect());

            foreach ($handledQueues as $queue) {
                if ($horizonQueuesForConnection->contains($queue)) {
                    continue;
                }

                $otherPlacements = $placementsByQueue
                    ->get($queue, collect())
                    ->filter(fn (array $p) => ($p['connection'] ?? '') !== $connectionName)
                    ->unique(fn (array $p) => ($p['connection'] ?? '')."\0".($p['supervisor'] ?? ''))
                    ->values();

                if ($otherPlacements->isNotEmpty()) {
                    $withHint[] = $this->formatMessageWithCrossConnectionHint(
                        $queue,
                        $connectionName,
                        $environment,
                        $otherPlacements
                    );
                } else {
                    $withoutHint[$connectionName][] = $queue;
                }
            }
        }

        $errors = array_values(array_merge(
            $this->formatGroupedWithoutHint($withoutHint, $environment),
            $withHint
        ));

        return EnvironmentCheckResult::errors($errors);
    }

    /**
     * Effective queues Horizon processes per connection (supervisor `queue` when set, else connection default).
     *
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return Collection<string, Collection<int, string>>
     */
    private function queuesHandledPerConnection(array $mergedHorizonSupervisors, array $queueConnections): Collection
    {
        $byConnection = Collection::make();

        foreach ($mergedHorizonSupervisors as $supervisorConfig) {
            if (! is_array($supervisorConfig)) {
                continue;
            }

            $connection = $supervisorConfig['connection'] ?? null;
            if (! is_string($connection) || $connection === '') {
                continue;
            }

            $queues = QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisorConfig, $queueConnections);

            $existing = $byConnection->get($connection, collect());
            $byConnection->put($connection, $existing->merge($queues)->unique()->sort(SORT_STRING)->values());
        }

        return $byConnection;
    }

    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return Collection<string, Collection<int, array{connection: string, supervisor: string}>>
     */
    private function horizonPlacementsByQueueName(array $mergedHorizonSupervisors, array $queueConnections): Collection
    {
        $byQueue = Collection::make();

        foreach ($mergedHorizonSupervisors as $supervisorKey => $supervisorConfig) {
            if (! is_array($supervisorConfig)) {
                continue;
            }

            $connection = $supervisorConfig['connection'] ?? null;
            if (! is_string($connection) || $connection === '') {
                continue;
            }

            $queues = QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisorConfig, $queueConnections);
            foreach ($queues as $queue) {
                $row = ['connection' => $connection, 'supervisor' => (string) $supervisorKey];
                $existing = $byQueue->get($queue, collect());
                $byQueue->put($queue, $existing->push($row));
            }
        }

        return $byQueue;
    }

    /**
     * @param  array<string, list<string>>  $withoutHint
     * @return list<string>
     */
    private function formatGroupedWithoutHint(array $withoutHint, string $environment): array
    {
        $messages = [];

        foreach ($withoutHint as $connectionName => $queues) {
            $queues = array_values(array_unique($queues));
            sort($queues, SORT_STRING);

            if ($queues === []) {
                continue;
            }

            if (count($queues) === 1) {
                $messages[] = $this->formatBareMismatchMessage($queues[0], $connectionName, $environment);

                continue;
            }

            $list = $this->formatQueueList($queues);
            $queueKey = "connections.{$connectionName}.queue";
            $messages[] = "Queues {$list} appear under `config/queue.php` → `{$queueKey}`, but in environment `{$environment}` no Horizon supervisor in `config/horizon.php` → `environments.{$environment}` uses connection `{$connectionName}` with those queues. Fix: add or adjust supervisors so one of them has `connection` `{$connectionName}` and `queue` includes {$list}, or remove those names from `{$queueKey}` if jobs should not use this connection. ".self::HORIZON_QUEUE_NOTE;
        }

        return $messages;
    }

    /**
     * @param  Collection<int, array{connection: string, supervisor: string}>  $otherPlacements
     */
    private function formatMessageWithCrossConnectionHint(
        string $queue,
        string $connectionName,
        string $environment,
        Collection $otherPlacements
    ): string {
        $lines = [];
        $byConn = $otherPlacements->groupBy('connection');

        foreach ($byConn as $horizonConnection => $rows) {
            $supervisors = $rows->pluck('supervisor')->unique()->sort()->values()->all();
            $supList = $this->formatSupervisorList($supervisors);
            $lines[] = "connection `{$horizonConnection}` ({$supList})";
        }

        $where = implode('; ', $lines);
        $queueKey = "connections.{$connectionName}.queue";
        $horizonEnvKey = "environments.{$environment}";

        return "Queue `{$queue}` is listed under `config/queue.php` → `{$queueKey}`, but in environment `{$environment}` no supervisor in `config/horizon.php` → `{$horizonEnvKey}` uses connection `{$connectionName}` for that queue. Horizon runs `{$queue}` on {$where} instead — a common mistake is putting the name on the wrong Redis connection in `config/queue.php`. Fix: if jobs are dispatched with queue connection `{$connectionName}`, add a supervisor under `{$horizonEnvKey}` with `connection` `{$connectionName}` and `queue` containing `{$queue}`; if jobs actually use the other connection, remove `{$queue}` from `{$queueKey}` so `config/queue.php` matches how you dispatch. ".self::HORIZON_QUEUE_NOTE;
    }

    private function formatBareMismatchMessage(string $queue, string $connectionName, string $environment): string
    {
        $queueKey = "connections.{$connectionName}.queue";
        $horizonEnvKey = "environments.{$environment}";

        return "Queue `{$queue}` is listed under `config/queue.php` → `{$queueKey}`, but in environment `{$environment}` no supervisor in `config/horizon.php` → `{$horizonEnvKey}` uses connection `{$connectionName}` with `{$queue}`. Fix: add a supervisor under `{$horizonEnvKey}` with `connection` `{$connectionName}` and `queue` including `{$queue}`, or remove `{$queue}` from `{$queueKey}` if nothing should run there. ".self::HORIZON_QUEUE_NOTE;
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
