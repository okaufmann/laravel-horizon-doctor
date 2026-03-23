<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Environment;

use Illuminate\Support\Collection;
use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;

final class RedisConnectionsUsedInHorizonCheck implements EnvironmentCheck
{
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections): array
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
            return [];
        }

        return [
            'Redis queue connection(s) '.implode(', ', $unused->all())." are not referenced by any Horizon supervisor in environment `{$environment}` (config/horizon.php).",
        ];
    }
}
