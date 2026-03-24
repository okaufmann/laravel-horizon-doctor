<?php

use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassAstAnalyzer;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassDiscovery;
use PhpParser\ParserFactory;

$testsRoot = dirname(__DIR__, 2);

it('extracts literal timeout and queue metadata', function () use ($testsRoot) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $discovery = new QueuedClassDiscovery($parser);
    $analyzer = new QueuedClassAstAnalyzer();

    $discovered = $discovery->discover($testsRoot, [
        'queued_class_paths' => ['Fixtures/QueuedClasses/Jobs'],
        'queued_class_exclude_patterns' => [],
    ]);

    $byFqn = [];
    foreach ($discovered as $d) {
        $byFqn[$d->fqn] = $d;
    }

    $timeoutMeta = $analyzer->analyze($byFqn['Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Jobs\TimeoutViolationJob']);
    expect($timeoutMeta->literalTimeout)->toBe(120)
        ->and($timeoutMeta->timeoutIsDynamic)->toBeFalse();

    $queueMeta = $analyzer->analyze($byFqn['Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Jobs\ExplicitQueueJob']);
    expect($queueMeta->literalQueue)->toBe('orphan-queue');

    $dynMeta = $analyzer->analyze($byFqn['Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Jobs\DynamicTimeoutJob']);
    expect($dynMeta->timeoutIsDynamic)->toBeTrue();
});

it('detects listener-shaped ctor onQueue pattern', function () use ($testsRoot) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $discovery = new QueuedClassDiscovery($parser);
    $analyzer = new QueuedClassAstAnalyzer();

    $discovered = $discovery->discover($testsRoot, [
        'queued_class_paths' => ['Fixtures/QueuedClasses/Listeners'],
        'queued_class_exclude_patterns' => [],
    ]);

    $byFqn = [];
    foreach ($discovered as $d) {
        $byFqn[$d->fqn] = $d;
    }

    $bad = $analyzer->analyze($byFqn['Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners\CtorOnQueueListener']);
    expect($bad->isListenerShaped)->toBeTrue()
        ->and($bad->hasOnQueueCallInConstructor)->toBeTrue()
        ->and($bad->hasOnQueueAttribute)->toBeFalse()
        ->and($bad->hasPublicQueuePropertyDefault)->toBeFalse();

    $good = $analyzer->analyze($byFqn['Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners\SafePropertyQueueListener']);
    expect($good->isListenerShaped)->toBeTrue()
        ->and($good->hasPublicQueuePropertyDefault)->toBeTrue();

    $attrListener = $analyzer->analyze($byFqn['Okaufmann\LaravelHorizonDoctor\Tests\Fixtures\QueuedClasses\Listeners\QueueAttributeListener']);
    expect($attrListener->hasOnQueueAttribute)->toBeTrue()
        ->and($attrListener->literalQueue)->toBe('emails');
});
