<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Contracts;

interface SupervisorCheck
{
    /**
     * @param  array<string, mixed>  $supervisorConfig
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return list<string>
     */
    public function check(string $environment, string $supervisorKey, array $supervisorConfig, array $queueConnections): array;
}
