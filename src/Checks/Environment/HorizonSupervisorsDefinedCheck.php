<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;

final class HorizonSupervisorsDefinedCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): array
    {
        if ($mergedHorizonSupervisors === []) {
            return [
                "Environment `{$environment}` defines no supervisors in config/horizon.php (after merging defaults).",
            ];
        }

        return [];
    }
}
