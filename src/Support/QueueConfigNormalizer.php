<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

use Illuminate\Support\Collection;

final class QueueConfigNormalizer
{
    /**
     * Queue names explicitly configured on a Laravel queue connection (config/queue.php → connections.*.queue).
     * When the key is absent, Redis uses `default` as the connection default queue name.
     *
     * @param  array<string, mixed>  $queueConnectionConfig
     * @return list<string>
     */
    public static function listedQueueNames(array $queueConnectionConfig): array
    {
        if (! array_key_exists('queue', $queueConnectionConfig)) {
            return ['default'];
        }

        $queue = $queueConnectionConfig['queue'];

        if ($queue === null || $queue === '') {
            return ['default'];
        }

        if (is_string($queue)) {
            return [$queue];
        }

        if (is_array($queue)) {
            return Collection::make($queue)
                ->flatten()
                ->filter(fn ($q) => is_string($q) && $q !== '')
                ->unique()
                ->sort(SORT_STRING)
                ->values()
                ->all();
        }

        return ['default'];
    }

    /**
     * Queue names a Horizon supervisor will actually process: non-empty `queue` in horizon.php, otherwise
     * the connection default from config/queue.php (same rules as {@see listedQueueNames}).
     *
     * @param  array<string, mixed>  $supervisorConfig
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return Collection<int, string>
     */
    public static function effectiveHorizonQueuesForSupervisor(array $supervisorConfig, array $queueConnections): Collection
    {
        $raw = $supervisorConfig['queue'] ?? null;

        $queues = Collection::make(is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [$raw] : []))
            ->flatten()
            ->filter(fn ($q) => is_string($q) && $q !== '');

        if ($queues->isNotEmpty()) {
            return $queues->unique()->sort(SORT_STRING)->values();
        }

        $connection = $supervisorConfig['connection'] ?? null;
        if (! is_string($connection) || $connection === '' || ! isset($queueConnections[$connection])) {
            return collect();
        }

        return Collection::make(self::listedQueueNames($queueConnections[$connection]))
            ->sort(SORT_STRING)
            ->values();
    }

    /**
     * Whether jobs dispatched on $connectionA and workers using $connectionB share the same Redis job storage
     * for a given queue name. Laravel names Redis lists `queues:{queue}`; the Laravel queue connection label
     * (`redis` vs `redis-long-running`) does not change that key when both connections use the same
     * `config/queue.php` → `connections.*.connection` Redis handle.
     *
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    public static function redisQueueConnectionsShareSameBackend(
        array $queueConnections,
        string $connectionA,
        string $connectionB,
    ): bool {
        if ($connectionA === $connectionB) {
            return true;
        }

        $a = $queueConnections[$connectionA] ?? null;
        $b = $queueConnections[$connectionB] ?? null;
        if (! is_array($a) || ! is_array($b)) {
            return false;
        }

        if (($a['driver'] ?? null) !== 'redis' || ($b['driver'] ?? null) !== 'redis') {
            return false;
        }

        $redisA = $a['connection'] ?? 'default';
        $redisB = $b['connection'] ?? 'default';

        return is_string($redisA) && is_string($redisB) && $redisA === $redisB;
    }
}
