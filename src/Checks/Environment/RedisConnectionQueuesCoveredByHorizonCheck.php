<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\QueueConfigNormalizer;

final class RedisConnectionQueuesCoveredByHorizonCheck implements EnvironmentCheck
{
    private const HORIZON_QUEUE_NOTE = 'Horizon uses each supervisor’s non-empty `queue` in `config/horizon.php`; if that list is empty, workers fall back to `connections.*.queue` in `config/queue.php` (default queue name / `RedisQueue` when no queue is passed).';

    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections, bool $verbose = false): EnvironmentCheckResult
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
                        $otherPlacements,
                        $verbose
                    );
                } else {
                    $withoutHint[$connectionName][] = $queue;
                }
            }
        }

        $errors = array_values(array_merge(
            $this->formatGroupedWithoutHint($withoutHint, $environment, $verbose),
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
    private function formatGroupedWithoutHint(array $withoutHint, string $environment, bool $verbose): array
    {
        $messages = [];

        foreach ($withoutHint as $connectionName => $queues) {
            $queues = array_values(array_unique($queues));
            sort($queues, SORT_STRING);

            if ($queues === []) {
                continue;
            }

            if (count($queues) === 1) {
                $messages[] = $this->formatBareMismatchMessage($queues[0], $connectionName, $environment, $verbose);

                continue;
            }

            $list = $this->formatQueueList($queues);
            $queueKey = "connections.{$connectionName}.queue";
            $short = "Queues {$list} are on `{$queueKey}` but no supervisor uses queue connection `{$connectionName}` in `environments.{$environment}`. Add supervisors or remove those names.";
            $messages[] = $verbose ? $short.' '.self::HORIZON_QUEUE_NOTE : $short;
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
        Collection $otherPlacements,
        bool $verbose
    ): string {
        $lines = [];
        $byConn = $otherPlacements->groupBy('connection');

        foreach ($byConn as $horizonConnection => $rows) {
            $supervisors = $rows->pluck('supervisor')->unique()->sort()->values()->all();
            $supList = $this->formatSupervisorList($supervisors);
            $lines[] = "`{$horizonConnection}` ({$supList})";
        }

        $where = implode('; ', $lines);
        $queueKey = "connections.{$connectionName}.queue";

        $short = "Queue `{$queue}` is on `{$queueKey}` but Horizon runs it on {$where}. Align `config/queue.php` with how jobs are dispatched, or add a supervisor on queue connection `{$connectionName}` in `environments.{$environment}`.";
        $long = " Full detail: no supervisor in `environments.{$environment}` uses Laravel queue connection `{$connectionName}` for this queue; jobs targeting that queue connection would not match Horizon’s supervisor `connection` labels. ".self::HORIZON_QUEUE_NOTE;

        return $verbose ? $short.$long : $short;
    }

    private function formatBareMismatchMessage(string $queue, string $connectionName, string $environment, bool $verbose): string
    {
        $queueKey = "connections.{$connectionName}.queue";
        $short = "Queue `{$queue}` is on `{$queueKey}` but no supervisor covers it on queue connection `{$connectionName}` in `environments.{$environment}`. Add a supervisor or remove the queue name.";
        $long = ' '.self::HORIZON_QUEUE_NOTE;

        return $verbose ? $short.$long : $short;
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
