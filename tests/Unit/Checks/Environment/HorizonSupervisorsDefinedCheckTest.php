<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Environment\HorizonSupervisorsDefinedCheck;

it('reports when no supervisors exist for an environment', function () {
    $result = (new HorizonSupervisorsDefinedCheck())->check('staging', [], []);

    expect($result->errors)->not->toBeEmpty();
    expect($result->warnings)->toBe([]);
    expect($result->errors[0])->toContain('staging');
    expect($result->errors[0])->toContain('environments.staging');
});

it('passes when supervisors are defined', function () {
    $result = (new HorizonSupervisorsDefinedCheck())->check('staging', ['supervisor-1' => []], []);

    expect($result->errors)->toBe([]);
    expect($result->warnings)->toBe([]);
});
