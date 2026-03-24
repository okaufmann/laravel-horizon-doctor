<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

final class QueuedClassScanCache
{
    /** @var list<QueuedClassStaticMetadata> */
    private array $metadata = [];

    private bool $scanRanThisRequest = false;

    /**
     * @param  list<QueuedClassStaticMetadata>  $metadata
     */
    public function set(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function markScanCompleted(): void
    {
        $this->scanRanThisRequest = true;
    }

    public function wasScanCompleted(): bool
    {
        return $this->scanRanThisRequest;
    }

    /**
     * @return list<QueuedClassStaticMetadata>
     */
    public function all(): array
    {
        return $this->metadata;
    }

    public function reset(): void
    {
        $this->metadata = [];
        $this->scanRanThisRequest = false;
    }
}
