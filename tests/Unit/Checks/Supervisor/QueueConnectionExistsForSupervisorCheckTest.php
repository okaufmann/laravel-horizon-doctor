<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\QueueConnectionExistsForSupervisorCheck;

it('reports a missing connection name', function () {
    $messages = (new QueueConnectionExistsForSupervisorCheck())->check(
        'local',
        'supervisor-1',
        [],
        ['redis' => ['driver' => 'redis']]
    );

    expect($messages)->not->toBeEmpty();
});

it('reports when the connection is not defined in queue config', function () {
    $messages = (new QueueConnectionExistsForSupervisorCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'missing'],
        ['redis' => ['driver' => 'redis']]
    );

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toContain('missing');
});

it('passes when the connection exists', function () {
    expect((new QueueConnectionExistsForSupervisorCheck())->check(
        'local',
        'supervisor-1',
        ['connection' => 'redis'],
        ['redis' => ['driver' => 'redis']]
    ))->toBe([]);
});
