<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Supervisor;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\SupervisorCheck;

final class SupervisorMaxProcessesPositiveCheck implements SupervisorCheck
{
    public function check(string $environment, string $supervisorKey, array $supervisorConfig, array $queueConnections): array
    {
        if (! array_key_exists('maxProcesses', $supervisorConfig)) {
            return [];
        }

        $max = $supervisorConfig['maxProcesses'];
        if (! is_numeric($max) || (int) $max >= 1) {
            return [];
        }

        return [
            "Horizon supervisor `{$supervisorKey}` (environment `{$environment}`) has `maxProcesses` set to ".var_export($max, true).'; it should be at least 1 or workers will not run.',
        ];
    }
}
