<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Supervisor\SupervisorTimeoutOptionPresentCheck;

it('reports when timeout is not set', function () {
    $messages = (new SupervisorTimeoutOptionPresentCheck())->check('local', 'supervisor-1', [], []);

    expect($messages)->not->toBeEmpty();
});

it('passes when timeout is present even if zero', function () {
    expect((new SupervisorTimeoutOptionPresentCheck())->check(
        'local',
        'supervisor-1',
        ['timeout' => 0],
        []
    ))->toBe([]);
});
