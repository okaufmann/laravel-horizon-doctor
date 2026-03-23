<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Supervisor;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;

final class SupervisorTimeoutLessThanQueueRetryAfterCheck implements SupervisorCheck
{
    public function check(string $environment, string $supervisorKey, array $supervisorConfig, array $queueConnections): array
    {
        $connection = $supervisorConfig['connection'] ?? null;
        if (! is_string($connection) || ! isset($queueConnections[$connection])) {
            return [];
        }

        $timeout = $supervisorConfig['timeout'] ?? null;
        $retryAfter = $queueConnections[$connection]['retry_after'] ?? null;

        if (! is_numeric($timeout) || ! is_numeric($retryAfter)) {
            return [];
        }

        $timeout = (int) $timeout;
        $retryAfter = (int) $retryAfter;

        if ($timeout >= $retryAfter) {
            return [
                "Horizon supervisor `{$supervisorKey}` (environment `{$environment}`) has `timeout` ({$timeout}) in config/horizon.php that must be strictly less than the queue connection's `retry_after` ({$retryAfter}) for `{$connection}` in config/queue.php — otherwise jobs may be released for retry while still running.",
            ];
        }

        return [];
    }
}
