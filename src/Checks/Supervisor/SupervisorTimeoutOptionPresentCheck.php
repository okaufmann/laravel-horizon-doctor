<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Supervisor;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;

final class SupervisorTimeoutOptionPresentCheck implements SupervisorCheck
{
    public function check(string $environment, string $supervisorKey, array $supervisorConfig, array $queueConnections): array
    {
        if (! array_key_exists('timeout', $supervisorConfig)) {
            return [
                "Consider setting the `timeout` option for Horizon supervisor `{$supervisorKey}` (environment `{$environment}`) in config/horizon.php.",
            ];
        }

        return [];
    }
}
