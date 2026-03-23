<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;

final class RedisConnectionQueuesCoveredByHorizonCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): array
    {
        $errors = [];

        $processedQueuesByConnection = $this->queuesHandledPerConnection($mergedHorizonSupervisors);

        $redisConnections = Collection::make($queueConnections)
            ->filter(fn ($config) => is_array($config) && ($config['driver'] ?? null) === 'redis');

        foreach ($redisConnections as $connectionName => $queueConfig) {
            $handledQueues = Collection::make($queueConfig['queue'] ?? []);
            $horizonQueuesForConnection = $processedQueuesByConnection->get($connectionName, collect());

            foreach ($handledQueues as $queue) {
                if (! $horizonQueuesForConnection->contains($queue)) {
                    $errors[] = "Queue `{$queue}` on Redis connection `{$connectionName}` is not assigned to any Horizon supervisor in environment `{$environment}` (config/horizon.php).";
                }
            }
        }

        return $errors;
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
