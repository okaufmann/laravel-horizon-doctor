<?php

use Okaufmann\LaravelHorizonDoctor\Checks\QueuedClasses\QueuedClassHorizonQueueCoverageCheck;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassScanCache;
use Okaufmann\LaravelHorizonDoctor\Support\QueuedClassStaticMetadata;

beforeEach(function () {
    config(['queue.default' => 'redis']);
});

it('warns when a statically known job queue is not supervised on that connection', function () {
    $cache = new QueuedClassScanCache();
    $cache->set([
        new QueuedClassStaticMetadata(
            fqn: 'App\\Jobs\\ExampleJob',
            filePath: '/app/Jobs/ExampleJob.php',
            literalQueue: 'missing-from-horizon',
            literalConnection: null,
            literalTimeout: null,
            timeoutIsDynamic: false,
            isListenerShaped: false,
            hasOnQueueAttribute: false,
            hasPublicQueuePropertyDefault: true,
            hasOnQueueCallInConstructor: false,
        ),
    ]);
    $cache->markScanCompleted();

    $check = new QueuedClassHorizonQueueCoverageCheck($cache);

    $merged = [
        'sup' => [
            'connection' => 'redis',
            'queue' => ['default'],
        ],
    ];

    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => 'default',
            'retry_after' => 90,
        ],
    ];

    $result = $check->check('local', $merged, $queueConnections);
    expect($result->warnings)->not->toBeEmpty();
});

it('does nothing when no scan was completed this run', function () {
    $cache = new QueuedClassScanCache();
    $check = new QueuedClassHorizonQueueCoverageCheck($cache);

    $result = $check->check('local', [], []);
    expect($result->warnings)->toBe([])
        ->and($result->errors)->toBe([]);
});
