<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorTimeoutLessThanQueueRetryAfterCheck;

it('reports when timeout is greater than or equal to retry_after', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'retry_after' => 90,
        ],
    ];

    expect((new SupervisorTimeoutLessThanQueueRetryAfterCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'redis', 'timeout' => 90],
        $queueConnections
    ))->not->toBeEmpty();

    expect((new SupervisorTimeoutLessThanQueueRetryAfterCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'redis', 'timeout' => 100],
        $queueConnections
    ))->not->toBeEmpty();
});

it('passes when timeout is strictly less than retry_after', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'retry_after' => 90,
        ],
    ];

    expect((new SupervisorTimeoutLessThanQueueRetryAfterCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'redis', 'timeout' => 60],
        $queueConnections
    ))->toBe([]);
});

it('does nothing when connection or retry values are missing', function () {
    expect((new SupervisorTimeoutLessThanQueueRetryAfterCheck())->check(
        'local',
        'supervisor-1',
        ['timeout' => 999],
        []
    ))->toBe([]);
});
