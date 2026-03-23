<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;

final class RedisConnectionQueuesCoveredByHorizonCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): array
    {
        $processedQueuesByConnection = $this->queuesHandledPerConnection($mergedHorizonSupervisors);
        $placementsByQueue = $this->horizonPlacementsByQueueName($mergedHorizonSupervisors);

        $redisConnections = Collection::make($queueConnections)
            ->filter(fn ($config) => is_array($config) && ($config['driver'] ?? null) === 'redis');

        $withHint = [];
        $withoutHint = [];

        foreach ($redisConnections as $connectionName => $queueConfig) {
            $handledQueues = Collection::make($queueConfig['queue'] ?? []);
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

        return array_values(array_merge(
            $this->formatGroupedWithoutHint($withoutHint, $environment),
            $withHint
        ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @return Collection<string, Collection<int, array{connection: string, supervisor: string}>>
     */
    private function horizonPlacementsByQueueName(array $mergedHorizonSupervisors): Collection
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

            $queues = Collection::make($supervisorConfig['queue'] ?? [])->flatten()->filter();
            foreach ($queues as $queue) {
                if (! is_string($queue) || $queue === '') {
                    continue;
                }

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
            $messages[] = "Queues {$list} are listed under queue connection `{$connectionName}` in config/queue.php, but no Horizon supervisor in environment `{$environment}` uses connection `{$connectionName}` with those queues. Add matching supervisors in config/horizon.php or adjust your queue connection configuration.";
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

        return "Queue `{$queue}` is listed under queue connection `{$connectionName}` in config/queue.php, but no Horizon supervisor in environment `{$environment}` uses `{$connectionName}` for that queue. The same queue name is assigned in Horizon on {$where}. If you dispatch jobs using the `{$connectionName}` connection, add a supervisor with connection `{$connectionName}` and this queue; if you dispatch using another Redis queue connection, remove the queue from `connections.{$connectionName}.queue` in config/queue.php so it matches reality.";
    }

    private function formatBareMismatchMessage(string $queue, string $connectionName, string $environment): string
    {
        return "Queue `{$queue}` is listed under queue connection `{$connectionName}` in config/queue.php, but no Horizon supervisor in environment `{$environment}` uses connection `{$connectionName}` with that queue. Add a supervisor in config/horizon.php or remove the queue from that connection if it is unused.";
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

    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @return Collection<string, Collection<int, string>>
     */
    private function queuesHandledPerConnection(array $mergedHorizonSupervisors): Collection
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

            $queues = Collection::make($supervisorConfig['queue'] ?? [])->flatten()->filter();

            $existing = $byConnection->get($connection, collect());
            $byConnection->put($connection, $existing->merge($queues)->unique()->values());
        }

        return $byConnection;
    }
}
