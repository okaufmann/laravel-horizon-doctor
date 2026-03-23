<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;

final class HorizonSupervisorsDefinedCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections, bool $verbose = false): EnvironmentCheckResult
    {
        if ($mergedHorizonSupervisors === []) {
            return EnvironmentCheckResult::errors([
                "Environment `{$environment}` has no supervisors after merging Horizon defaults. Add at least one under `environments.{$environment}` in `config/horizon.php`.",
            ]);
        }

        return EnvironmentCheckResult::ok();
    }
}
