<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;

final class HorizonSupervisorsDefinedCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): EnvironmentCheckResult
    {
        if ($mergedHorizonSupervisors === []) {
            return EnvironmentCheckResult::errors([
                "Environment `{$environment}` has no supervisors after merging `config/horizon.php` defaults into `environments.{$environment}`. Fix: add at least one supervisor under `config/horizon.php` → `environments.{$environment}`.",
            ]);
        }

        return EnvironmentCheckResult::ok();
    }
}
