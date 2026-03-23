<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;

final class RedisConnectionsUsedInHorizonCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): EnvironmentCheckResult
    {
        $redisConnectionNames = Collection::make($queueConnections)
            ->filter(fn ($queue) => is_array($queue) && ($queue['driver'] ?? null) === 'redis')
            ->keys()
            ->values();

        $usedConnections = Collection::make($mergedHorizonSupervisors)
            ->map(fn ($queue) => is_array($queue) ? ($queue['connection'] ?? null) : null)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        $unused = $redisConnectionNames->diff($usedConnections);

        if ($unused->isEmpty()) {
            return EnvironmentCheckResult::ok();
        }

        $list = $unused->sort()->values()->implode('`, `');

        return EnvironmentCheckResult::errors([
            "Redis queue connection(s) `{$list}` are defined in `config/queue.php` under `connections.*` but none are referenced by any Horizon supervisor in environment `{$environment}` (`config/horizon.php` → `environments.{$environment}` → supervisor `connection`). Fix: add a supervisor that sets `connection` to each unused Redis connection, or remove/rename the unused connection in `config/queue.php` if it is obsolete.",
        ]);
    }
}
