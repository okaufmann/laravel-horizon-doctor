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

    $result = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', $supervisors, $queueConnections);

    expect($result->errors)->toHaveCount(1);
    expect($result->warnings)->toBe([]);
    expect($result->errors[0])->toContain('emails');
    expect($result->errors[0])->toContain('redis');
    expect($result->errors[0])->toContain('connections.redis.queue');
    expect($result->errors[0])->toContain('environments.local');
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

    $result = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('production', $supervisors, $queueConnections);

    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0])->toContain('document-generation');
    expect($result->errors[0])->toContain('redis-long-running');
    expect($result->errors[0])->toContain('supervisor-long');
    expect($result->errors[0])->toContain('dispatched');
    expect($result->errors[0])->toContain('connections.redis.queue');
    expect($result->errors[0])->toContain('environments.production');
});

it('groups multiple uncovered queues on the same connection into one message', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['a', 'b', 'c'],
        ],
    ];

    $result = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', [], $queueConnections);

    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0])->toContain('`a`', '`b`', '`c`');
    expect($result->errors[0])->toContain('connections.redis.queue');
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

    $result = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', $supervisors, $queueConnections);

    expect($result->errors)->toBe([]);
    expect($result->warnings)->toBe([]);
});

it('ignores non-redis connections', function () {
    $queueConnections = [
        'database' => [
            'driver' => 'database',
            'queue' => ['default'],
        ],
    ];

    $result = (new RedisConnectionQueuesCoveredByHorizonCheck())->check('local', [], $queueConnections);

    expect($result->errors)->toBe([]);
    expect($result->warnings)->toBe([]);
});
