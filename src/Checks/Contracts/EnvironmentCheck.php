<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Contracts;

use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;

interface EnvironmentCheck
{
    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections, bool $verbose = false): EnvironmentCheckResult;
}
