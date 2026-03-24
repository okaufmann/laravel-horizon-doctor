<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\EnvironmentCheck;
use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\QueueConfigNormalizer;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassScanCache;

final class QueuedClassHorizonQueueCoverageCheck implements EnvironmentCheck
{
    public function __construct(
        private readonly QueuedClassScanCache $cache,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    public function check(string $environment, array $mergedHorizonSupervisors, array $queueConnections, bool $verbose = false): EnvironmentCheckResult
    {
        if (! $this->cache->wasScanCompleted()) {
            return EnvironmentCheckResult::ok();
        }

        $warnings = [];
        $defaultConnection = config('queue.default');
        if (! is_string($defaultConnection) || $defaultConnection === '') {
            $defaultConnection = 'redis';
        }

        foreach ($this->cache->all() as $meta) {
            if ($meta->literalQueue === null || $meta->literalQueue === '') {
                continue;
            }

            $connectionName = $meta->literalConnection ?? $defaultConnection;
            if (! isset($queueConnections[$connectionName]) || ! is_array($queueConnections[$connectionName])) {
                continue;
            }

            if (($queueConnections[$connectionName]['driver'] ?? null) !== 'redis') {
                continue;
            }

            if ($this->horizonSupervisesQueue($meta->literalQueue, $connectionName, $mergedHorizonSupervisors, $queueConnections)) {
                continue;
            }

            $warnings[] = "Queued class `{$meta->fqn}` in `{$meta->filePath}` targets Redis queue `{$meta->literalQueue}` on connection `{$connectionName}`, but no Horizon supervisor in environment `{$environment}` processes that queue on that connection. Add a supervisor or align `config/horizon.php` → `environments.{$environment}`.";
        }

        return EnvironmentCheckResult::warnings($warnings);
    }

    /**
     * @param  array<string, array<string, mixed>>  $mergedHorizonSupervisors
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    private function horizonSupervisesQueue(string $queueName, string $connectionName, array $mergedHorizonSupervisors, array $queueConnections): bool
    {
        foreach ($mergedHorizonSupervisors as $supervisorConfig) {
            if (! is_array($supervisorConfig)) {
                continue;
            }

            $supervisorConnection = $supervisorConfig['connection'] ?? null;
            if (! is_string($supervisorConnection)
                || $supervisorConnection === ''
                || ! QueueConfigNormalizer::redisQueueConnectionsShareSameBackend(
                    $queueConnections,
                    $connectionName,
                    $supervisorConnection,
                )) {
                continue;
            }

            $queues = QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisorConfig, $queueConnections);
            if ($queues->contains($queueName)) {
                return true;
            }
        }

        return false;
    }
}
