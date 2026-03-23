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
                "Horizon supervisor `{$supervisorKey}` (environment `{$environment}`) has no valid `connection` in config/horizon.php.",
            ];
        }

        if (! isset($queueConnections[$connection])) {
            return [
                "Connection `{$connection}` referenced by Horizon supervisor `{$supervisorKey}` (environment `{$environment}`) does not exist in config/queue.php.",
            ];
        }

        return [];
    }
}
