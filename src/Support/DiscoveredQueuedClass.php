<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

use PhpParser\Node\Stmt\Class_;

final readonly class DiscoveredQueuedClass
{
    public function __construct(
        public string $fqn,
        public string $filePath,
        public Class_ $classNode,
    ) {}
}
