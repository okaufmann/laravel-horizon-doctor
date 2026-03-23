<?php

use Okaufmann\LaravelHorizonDoctor\Support\HorizonConfigMerger;

it('merges horizon defaults into environment supervisor options', function () {
    config([
        'horizon.defaults' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'timeout' => 60,
            ],
        ],
        'horizon.environments.production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
            ],
        ],
    ]);

    $merged = (new HorizonConfigMerger())->mergeSupervisorsForEnvironment('production');

    expect($merged['supervisor-1']['connection'])->toBe('redis');
    expect($merged['supervisor-1']['queue'])->toBe(['default']);
    expect($merged['supervisor-1']['timeout'])->toBe(60);
    expect($merged['supervisor-1']['maxProcesses'])->toBe(10);
});

it('returns an empty array when defaults or environment supervisors are not arrays', function () {
    config([
        'horizon.defaults' => null,
        'horizon.environments.production' => ['supervisor-1' => []],
    ]);

    expect((new HorizonConfigMerger())->mergeSupervisorsForEnvironment('production'))->toBe([]);
});
