<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\RedisConnectionsUsedInHorizonCheck;

it('reports redis connections that are not referenced by any horizon supervisor', function () {
    $queueConnections = [
        'redis' => ['driver' => 'redis', 'queue' => 'default'],
        'redis-alt' => ['driver' => 'redis', 'queue' => 'default'],
    ];

    $supervisors = [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
        ],
    ];

    $messages = (new RedisConnectionsUsedInHorizonCheck())->check('local', $supervisors, $queueConnections);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('redis-alt');
});

it('passes when every redis connection is referenced', function () {
    $queueConnections = [
        'redis' => ['driver' => 'redis', 'queue' => 'default'],
    ];

    $supervisors = [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
        ],
    ];

    expect((new RedisConnectionsUsedInHorizonCheck())->check('local', $supervisors, $queueConnections))->toBe([]);
});
