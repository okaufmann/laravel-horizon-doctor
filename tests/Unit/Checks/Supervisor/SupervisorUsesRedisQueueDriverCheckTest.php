<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorUsesRedisQueueDriverCheck;

it('reports when the queue connection is not redis', function () {
    $queueConnections = [
        'sync' => ['driver' => 'sync'],
    ];

    $messages = (new SupervisorUsesRedisQueueDriverCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'sync'],
        $queueConnections
    );

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('sync');
});

it('passes for redis driver connections', function () {
    $queueConnections = [
        'redis' => ['driver' => 'redis'],
    ];

    expect((new SupervisorUsesRedisQueueDriverCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'redis'],
        $queueConnections
    ))->toBe([]);
});

it('skips when the connection is unknown', function () {
    expect((new SupervisorUsesRedisQueueDriverCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'missing'],
        []
    ))->toBe([]);
});
