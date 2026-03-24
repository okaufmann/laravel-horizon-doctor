<?php

use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassDiscovery;
use PhpParser\ParserFactory;

$testsRoot = dirname(__DIR__, 2);

it('discovers queued classes under configured paths', function () use ($testsRoot) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $discovery = new QueuedClassDiscovery($parser);

    $discovered = $discovery->discover($testsRoot, [
        'queued_class_paths' => ['Fixtures/QueuedClasses'],
        'queued_class_exclude_patterns' => [],
    ]);

    $fqns = array_map(fn ($d) => $d->fqn, $discovered);

    expect($fqns)->toContain('Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Jobs\TimeoutViolationJob')
        ->and($fqns)->toContain('Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners\CtorOnQueueListener');
});
