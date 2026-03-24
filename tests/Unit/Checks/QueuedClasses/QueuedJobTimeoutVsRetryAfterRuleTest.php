<?php

use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedJobTimeoutVsRetryAfterRule;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassAstAnalyzer;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassDiscovery;
use PhpParser\ParserFactory;

$testsRoot = dirname(__DIR__, 3);

beforeEach(function () {
    config(['queue.default' => 'redis']);
});

it('errors when job timeout is not strictly less than redis retry_after', function () use ($testsRoot) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $discovery = new QueuedClassDiscovery($parser);
    $analyzer = new QueuedClassAstAnalyzer();
    $rule = new QueuedJobTimeoutVsRetryAfterRule();

    $discovered = $discovery->discover($testsRoot, [
        'queued_class_paths' => ['Fixtures/QueuedClasses/Jobs'],
        'queued_class_exclude_patterns' => [],
    ]);

    $meta = null;
    foreach ($discovered as $d) {
        if (str_ends_with($d->fqn, 'TimeoutViolationJob')) {
            $meta = $analyzer->analyze($d);
            break;
        }
    }
    expect($meta)->not->toBeNull();

    $queueConnections = [
        'redis' => ['driver' => 'redis', 'retry_after' => 90],
    ];

    $result = $rule->check([$meta], $queueConnections, strictJobTimeouts: true);
    expect($result->errors)->not->toBeEmpty()
        ->and($result->warnings)->toBe([]);
});

it('warns instead of error when strict_job_timeouts is false', function () use ($testsRoot) {
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $discovery = new QueuedClassDiscovery($parser);
    $analyzer = new QueuedClassAstAnalyzer();
    $rule = new QueuedJobTimeoutVsRetryAfterRule();

    $discovered = $discovery->discover($testsRoot, [
        'queued_class_paths' => ['Fixtures/QueuedClasses/Jobs'],
        'queued_class_exclude_patterns' => [],
    ]);

    $meta = null;
    foreach ($discovered as $d) {
        if (str_ends_with($d->fqn, 'TimeoutViolationJob')) {
            $meta = $analyzer->analyze($d);
            break;
        }
    }
    expect($meta)->not->toBeNull();
    $queueConnections = [
        'redis' => ['driver' => 'redis', 'retry_after' => 90],
    ];

    $result = $rule->check([$meta], $queueConnections, strictJobTimeouts: false);
    expect($result->warnings)->not->toBeEmpty()
        ->and($result->errors)->toBe([]);
});
