<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Contracts;

interface EnvironmentCheck
{
    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return list<string>
     */
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): array;
}
