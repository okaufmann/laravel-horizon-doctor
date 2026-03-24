<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

final readonly class QueuedClassStaticMetadata
{
    /**
     * @param  list<int>  $timeoutLineNumbers
     */
    public function __construct(
        public string $fqn,
        public string $filePath,
        public ?string $literalQueue,
        public ?string $literalConnection,
        public ?int $literalTimeout,
        public bool $timeoutIsDynamic,
        public bool $isListenerShaped,
        public bool $hasOnQueueAttribute,
        public bool $hasPublicQueuePropertyDefault,
        public bool $hasOnQueueCallInConstructor,
        public array $timeoutLineNumbers = [],
    ) {}
}
