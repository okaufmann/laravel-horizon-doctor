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
