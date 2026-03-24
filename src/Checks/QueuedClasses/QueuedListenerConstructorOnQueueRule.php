<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses;

use Okaufmann\LaravelHorizonDoctor\Checks\EnvironmentCheckResult;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassStaticMetadata;

final class QueuedListenerConstructorOnQueueRule
{
    /**
     * @param  list<QueuedClassStaticMetadata>  $metadata
     */
    public function check(array $metadata): EnvironmentCheckResult
    {
        $warnings = [];

        foreach ($metadata as $meta) {
            if (! $meta->isListenerShaped) {
                continue;
            }

            if ($meta->hasOnQueueAttribute || $meta->hasPublicQueuePropertyDefault) {
                continue;
            }

            if (! $meta->hasOnQueueCallInConstructor) {
                continue;
            }

            $warnings[] = "Queued listener-shaped class `{$meta->fqn}` in `{$meta->filePath}` calls `onQueue()` only from `__construct`. For event listeners that implement `ShouldQueue`, the constructor may not run as expected when the listener is queued; prefer `public \$queue`, or `#[Queue(...)]`, or ensure the queue is set when dispatching.";
        }

        return EnvironmentCheckResult::warnings($warnings);
    }
}
