<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Supervisor;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;

final class QueueConnectionExistsForSupervisorCheck implements SupervisorCheck
{
    public function check(string $environment, string $supervisorKey, array $supervisorConfig, array $queueConnections): array
    {
        $connection = $supervisorConfig['connection'] ?? null;

        if (! is_string($connection) || $connection === '') {
            return [
                "Horizon supervisor `{$supervisorKey}` in environment `{$environment}` has no valid `connection` in `config/horizon.php` → `environments.{$environment}.{$supervisorKey}`. Fix: set `connection` to a key from `config/queue.php` → `connections`.",
            ];
        }

        if (! isset($queueConnections[$connection])) {
            return [
                "Supervisor `{$supervisorKey}` (environment `{$environment}`) uses `connection` `{$connection}` in `config/horizon.php` → `environments.{$environment}.{$supervisorKey}`, but that key is missing from `config/queue.php` → `connections`. Fix: add `connections.{$connection}` or point the supervisor at an existing connection name.",
            ];
        }

        return [];
    }
}
