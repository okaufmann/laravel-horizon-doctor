<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\RedisConnectionQueuesCoveredByHorizonCheck;

it('reports queue names that are not handled by any supervisor for that redis connection', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['default', 'emails'],
        ],
    ];

    $supervisors = [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
        ],
    ];

    $messages = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', $supervisors, $queueConnections);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('emails');
    expect($messages[0])->toContain('redis');
    expect($messages[0])->toContain('config/queue.php');
    expect($messages[0])->toContain('config/horizon.php');
});

it('hints when the same queue is covered by horizon on a different redis queue connection', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['document-generation'],
        ],
        'redis-long-running' => [
            'driver' => 'redis',
            'queue' => ['participant-imports'],
        ],
    ];

    $supervisors = [
        'supervisor-main' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
        ],
        'supervisor-long' => [
            'connection' => 'redis-long-running',
            'queue' => ['document-generation', 'participant-imports'],
        ],
    ];

    $messages = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('production', $supervisors, $queueConnections);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('document-generation');
    expect($messages[0])->toContain('redis-long-running');
    expect($messages[0])->toContain('supervisor-long');
    expect($messages[0])->toContain('dispatch');
});

it('groups multiple uncovered queues on the same connection into one message', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['a', 'b', 'c'],
        ],
    ];

    $messages = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', [], $queueConnections);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('`a`', '`b`', '`c`');
});

it('matches supervisors to the correct redis connection when multiple exist', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['default'],
        ],
        'redis-long' => [
            'driver' => 'redis',
            'queue' => ['long-running'],
        ],
    ];

    $supervisors = [
        'default-worker' => [
            'connection' => 'redis',
            'queue' => ['default'],
        ],
        'long-worker' => [
            'connection' => 'redis-long',
            'queue' => ['long-running'],
        ],
    ];

    expect((new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', $supervisors, $queueConnections))->toBe([]);
});

it('ignores non-redis connections', function () {
    $queueConnections = [
        'database' => [
            'driver' => 'database',
            'queue' => ['default'],
        ],
    ];

    expect((new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', [], $queueConnections))->toBe([]);
});
