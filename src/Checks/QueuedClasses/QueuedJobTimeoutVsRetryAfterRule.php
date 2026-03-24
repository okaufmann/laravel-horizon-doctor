<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses;

use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassStaticMetadata;

final class QueuedJobTimeoutVsRetryAfterRule
{
    /**
     * @param  list<QueuedClassStaticMetadata>  $metadata
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    public function check(array $metadata, array $queueConnections, bool $strictJobTimeouts): EnvironmentCheckResult
    {
        $errors = [];
        $warnings = [];

        $defaultConnection = config('queue.default');
        if (! is_string($defaultConnection)) {
            $defaultConnection = 'redis';
        }

        foreach ($metadata as $meta) {
            if ($meta->timeoutIsDynamic) {
                $lineHint = $meta->timeoutLineNumbers !== []
                    ? ' (see line '.implode(', ', $meta->timeoutLineNumbers).')'
                    : '';
                $warnings[] = "Queued class `{$meta->fqn}` in `{$meta->filePath}` declares a non-literal timeout{$lineHint}; Horizon Doctor cannot compare it to `retry_after` on your Redis queue connection. Verify manually that job timeout stays strictly below `retry_after`.";
            }

            if ($meta->literalTimeout === null) {
                continue;
            }

            $timeout = $meta->literalTimeout;
            $connectionNames = [];
            if ($meta->literalConnection !== null && $meta->literalConnection !== '') {
                $connectionNames[] = $meta->literalConnection;
            } else {
                $connectionNames[] = $defaultConnection;
            }

            foreach ($connectionNames as $connectionName) {
                if (! isset($queueConnections[$connectionName]) || ! is_array($queueConnections[$connectionName])) {
                    continue;
                }

                $cfg = $queueConnections[$connectionName];
                if (($cfg['driver'] ?? null) !== 'redis') {
                    continue;
                }

                $retryAfter = $cfg['retry_after'] ?? null;
                if (! is_numeric($retryAfter)) {
                    continue;
                }

                $retryAfter = (int) $retryAfter;
                if ($timeout >= $retryAfter) {
                    $lineHint = $meta->timeoutLineNumbers !== []
                        ? ' (around line '.implode(', ', $meta->timeoutLineNumbers).')'
                        : '';
                    $msg = "Queued class `{$meta->fqn}` in `{$meta->filePath}` uses timeout {$timeout}s{$lineHint}, which must be strictly less than `retry_after` ({$retryAfter}) on Redis queue connection `{$connectionName}` in config/queue.php — otherwise jobs may be released for retry while still running.";

                    if ($strictJobTimeouts) {
                        $errors[] = $msg;
                    } else {
                        $warnings[] = $msg;
                    }
                }
            }
        }

        return new EnvironmentCheckResult($errors, $warnings);
    }
}
