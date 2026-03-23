<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;

final class RedisConnectionsUsedInHorizonCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections, bool $verbose = false): EnvironmentCheckResult
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

        $short = "Redis connection(s) `{$list}` in `config/queue.php` are unused by any supervisor in `environments.{$environment}`. Add a supervisor or remove the connection.";
        $long = ' (`config/horizon.php` → supervisor `connection`.)';

        return EnvironmentCheckResult::errors([
            $verbose ? $short.$long : $short,
        ]);
    }
}
