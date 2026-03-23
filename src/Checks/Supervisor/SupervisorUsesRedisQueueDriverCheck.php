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

        $driverLabel = is_string($driver) ? $driver : 'unknown';

        return [
            "Supervisor `{$supervisorKey}` (environment `{$environment}`) sets `connection` `{$connection}` in `config/horizon.php` → `environments.{$environment}.{$supervisorKey}`, but `config/queue.php` → `connections.{$connection}.driver` is `{$driverLabel}`, not `redis`. Horizon targets Redis queues; use a Redis connection or run `queue:work` for other drivers.",
        ];
    }
}
