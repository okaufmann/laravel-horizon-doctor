<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Supervisor;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;

final class SupervisorUsesRedisQueueDriverCheck implements SupervisorCheck
{
    public function check(string $environment, string $supervisorKey, array $supervisorConfig, array $queueConnections): array
    {
        $connection = $supervisorConfig['connection'] ?? null;
        if (! is_string($connection) || ! isset($queueConnections[$connection])) {
            return [];
        }

        $driver = $queueConnections[$connection]['driver'] ?? null;
        if ($driver === 'redis') {
            return [];
        }

        return [
            "Horizon supervisor `{$supervisorKey}` (environment `{$environment}`) uses queue connection `{$connection}` whose driver is `".(is_string($driver) ? $driver : 'unknown').'`, not `redis`. Horizon is intended for Redis queues; other drivers usually need `queue:work`, not Horizon.',
        ];
    }
}
