<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\HorizonSupervisorsDefinedCheck;

it('reports when no supervisors exist for an environment', function () {
    $messages = (new HorizonSupervisorsDefinedCheck())->check('staging', [], []);

    expect($messages)->not->toBeEmpty();
    expect($messages[0])->toContain('staging');
});

it('passes when supervisors are defined', function () {
    expect((new HorizonSupervisorsDefinedCheck())->check('staging', ['supervisor-1' => []], []))->toBe([]);
});
