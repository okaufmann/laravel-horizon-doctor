<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorMaxProcessesPositiveCheck;

it('reports when maxProcesses is below one', function () {
    expect((new SupervisorMaxProcessesPositiveCheck())->check(
        'local',
        'supervisor-1',
        ['maxProcesses' => 0],
        []
    ))->not->toBeEmpty();
});

it('passes when maxProcesses is omitted', function () {
    expect((new SupervisorMaxProcessesPositiveCheck())->check(
        'local',
        'supervisor-1',
        [],
        []
    ))->toBe([]);
});

it('passes when maxProcesses is at least one', function () {
    expect((new SupervisorMaxProcessesPositiveCheck())->check(
        'local',
        'supervisor-1',
        ['maxProcesses' => 1],
        []
    ))->toBe([]);
});
