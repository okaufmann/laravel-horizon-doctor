<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\HorizonQueuesDocumentedInQueuePhpCheck;

it('warns when horizon supervises a queue not listed on the same connection in queue.php', function () {
    $queueConnections = [
        'redis-long-running' => [
            'driver' => 'redis',
            'queue' => ['participant-imports'],
        ],
    ];

    $supervisors = [
        'supervisor-long' => [
            'connection' => 'redis-long-running',
            'queue' => ['document-generation', 'participant-imports'],
        ],
    ];

    $result = (new HorizonQueuesDocumentedInQueuePhpCheck())->check('production', $supervisors, $queueConnections);

    expect($result->errors)->toBe([]);
    expect($result->warnings)->toHaveCount(1);
    expect($result->warnings[0])->toContain('document-generation');
    expect($result->warnings[0])->toContain('supervisor-long');
    expect($result->warnings[0])->toContain('connections.redis-long-running.queue');
    expect($result->warnings[0])->toContain('environments.production');
    expect($result->warnings[0])->toContain('participant-imports');
});

it('passes when every horizon queue on a connection is listed in queue.php', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['default', 'mail'],
        ],
    ];

    $supervisors = [
        'worker' => [
            'connection' => 'redis',
            'queue' => ['default', 'mail'],
        ],
    ];

    $result = (new HorizonQueuesDocumentedInQueuePhpCheck())->check('local', $supervisors, $queueConnections);

    expect($result->errors)->toBe([]);
    expect($result->warnings)->toBe([]);
});

it('uses queue.php defaults when supervisor queue is empty', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['default'],
        ],
    ];

    $supervisors = [
        'worker' => [
            'connection' => 'redis',
            'queue' => [],
        ],
    ];

    expect((new HorizonQueuesDocumentedInQueuePhpCheck())->check('local', $supervisors, $queueConnections)->warnings)->toBe([]);
});

it('ignores non-redis connections', function () {
    $queueConnections = [
        'sync' => [
            'driver' => 'sync',
            'queue' => ['default'],
        ],
    ];

    $supervisors = [
        'worker' => [
            'connection' => 'sync',
            'queue' => ['default'],
        ],
    ];

    expect((new HorizonQueuesDocumentedInQueuePhpCheck())->check('local', $supervisors, $queueConnections)->warnings)->toBe([]);
});
