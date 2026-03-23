<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

use Illuminate\Support\Collection;

/**
 * Builds rows for a per-environment view of Redis queue names vs config/queue.php and Horizon supervisors.
 *
 * @phpstan-type OverviewRow array{
 *     queue: string,
 *     connection: string,
 *     queue_php: string,
 *     horizon: string,
 *     status: string,
 * }
 */
final class RedisQueueHorizonOverview
{
    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     * @return list<OverviewRow>
     */
    public static function rows(array $mergedHorizonSupervisors, array $queueConnections): array
    {
        $redisConnections = Collection::make($queueConnections)
            ->filter(fn ($cfg) => is_array($cfg) && ($cfg['driver'] ?? null) === 'redis')
            ->keys()
            ->all();

        /** @var array<string, array<string, list<string>>> $horizonByConnection queue => supervisor keys */
        $horizonByConnection = [];

        foreach ($mergedHorizonSupervisors as $supervisorKey => $supervisorConfig) {
            if (! is_array($supervisorConfig)) {
                continue;
            }

            $connection = $supervisorConfig['connection'] ?? null;
            if (! is_string($connection) || $connection === '') {
                continue;
            }

            $queues = QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisorConfig, $queueConnections);
            foreach ($queues as $queue) {
                $horizonByConnection[$connection][$queue][] = (string) $supervisorKey;
            }
        }

        /** @var array<string, list<string>> $queueToHorizonConnections */
        $queueToHorizonConnections = [];
        foreach ($horizonByConnection as $connectionName => $byQueue) {
            foreach (array_keys($byQueue) as $queueName) {
                $queueToHorizonConnections[$queueName][] = $connectionName;
            }
        }

        /** @var array<string, array{connection: string, queue: string}> $pairKeys */
        $pairs = [];

        foreach ($redisConnections as $connectionName) {
            $cfg = $queueConnections[$connectionName] ?? null;
            if (! is_array($cfg)) {
                continue;
            }

            foreach (QueueConfigNormalizer::listedQueueNames($cfg) as $queue) {
                $k = self::pairKey($connectionName, $queue);
                $pairs[$k] = ['connection' => $connectionName, 'queue' => $queue];
            }
        }

        foreach ($horizonByConnection as $connectionName => $byQueue) {
            if (! in_array($connectionName, $redisConnections, true)) {
                continue;
            }

            foreach (array_keys($byQueue) as $queue) {
                $k = self::pairKey($connectionName, $queue);
                if (! isset($pairs[$k])) {
                    $pairs[$k] = ['connection' => $connectionName, 'queue' => $queue];
                }
            }
        }

        $rows = [];
        foreach ($pairs as $pair) {
            $connectionName = $pair['connection'];
            $queue = $pair['queue'];
            $cfg = $queueConnections[$connectionName] ?? null;
            $listed = is_array($cfg) && in_array($queue, QueueConfigNormalizer::listedQueueNames($cfg), true);

            $supervisorsOnConn = $horizonByConnection[$connectionName][$queue] ?? [];
            $supervisorsOnConn = array_values(array_unique($supervisorsOnConn));
            sort($supervisorsOnConn, SORT_STRING);
            $horizonHere = $supervisorsOnConn !== [];

            $queuePhpCell = $listed ? 'yes' : 'no';
            $horizonCell = $horizonHere ? implode(', ', $supervisorsOnConn) : '—';

            if ($listed && $horizonHere) {
                $status = 'OK';
            } elseif ($listed && ! $horizonHere) {
                $other = array_values(array_diff($queueToHorizonConnections[$queue] ?? [], [$connectionName]));
                sort($other, SORT_STRING);
                if ($other !== []) {
                    $status = 'Error: wrong connection — Horizon uses `'.implode('`, `', $other).'` only';
                } else {
                    $status = 'Error: in queue.php but no supervisor on this connection';
                }
            } else {
                $status = 'Warning: not listed under connections.'.$connectionName.'.queue';
            }

            $rows[] = [
                'queue' => $queue,
                'connection' => $connectionName,
                'queue_php' => $queuePhpCell,
                'horizon' => $horizonCell,
                'status' => $status,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['connection'], $a['queue']] <=> [$b['connection'], $b['queue']];
        });

        return $rows;
    }

    /**
     * @param  list<OverviewRow>  $rows
     * @return list<list<string>>
     */
    public static function toTableBody(array $rows): array
    {
        return array_map(
            fn (array $r) => [
                $r['queue'],
                $r['connection'],
                $r['queue_php'],
                $r['horizon'],
                $r['status'],
            ],
            $rows
        );
    }

    private static function pairKey(string $connection, string $queue): string
    {
        return $connection."\0".$queue;
    }
}
