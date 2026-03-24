<?php

namespace Okaufmann\LaravelHorizonDoctor;

use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedJobTimeoutVsRetryAfterRule;
use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedListenerConstructorOnQueueRule;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassAstAnalyzer;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassDiscovery;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassScanCache;

final class QueuedClassScanRunner
{
    public function __construct(
        private readonly QueuedClassDiscovery $discovery,
        private readonly QueuedClassAstAnalyzer $analyzer,
        private readonly QueuedClassScanCache $cache,
        private readonly QueuedJobTimeoutVsRetryAfterRule $timeoutRule,
        private readonly QueuedListenerConstructorOnQueueRule $listenerRule,
    ) {}

    /**
     * @param  array<string, mixed>  $horizonDoctorConfig
     * @param  array<string, array<string, mixed>>  $queueConnections
     */
    public function discoverAndRunRules(string $basePath, array $horizonDoctorConfig, array $queueConnections): EnvironmentCheckResult
    {
        $discovered = $this->discovery->discover($basePath, $horizonDoctorConfig);
        $metadata = [];
        foreach ($discovered as $class) {
            $metadata[] = $this->analyzer->analyze($class);
        }

        $this->cache->set($metadata);
        $this->cache->markScanCompleted();

        $strictJobTimeouts = true;
        if (array_key_exists('strict_job_timeouts', $horizonDoctorConfig)) {
            $strictJobTimeouts = (bool) $horizonDoctorConfig['strict_job_timeouts'];
        }

        $result = EnvironmentCheckResult::ok();
        $result = $result->merge($this->timeoutRule->check($metadata, $queueConnections, $strictJobTimeouts));

        return $result->merge($this->listenerRule->check($metadata));
    }
}
