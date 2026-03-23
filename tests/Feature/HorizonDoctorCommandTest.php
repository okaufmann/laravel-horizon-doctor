<?php

use Okaufmann\LaravelHorizonDoctor\HorizonDoctorRunner;

it('runs the horizon doctor command against valid config', function () {
    config([
        'horizon.environments' => [
            'local' => [
                'supervisor-1' => [
                    'maxProcesses' => 1,
                ],
            ],
        ],
        'horizon.defaults' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'timeout' => 60,
                'tries' => 1,
            ],
        ],
        'queue.connections' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
            ],
        ],
    ]);

    $this->artisan('horizon:doctor')->assertExitCode(0);
});

it('registers the horizon doctor runner in the container', function () {
    expect(app(HorizonDoctorRunner::class))->toBeInstanceOf(HorizonDoctorRunner::class);
});

it('exits with failure when horizon environments are not configured', function () {
    config(['horizon.environments' => []]);

    $this->artisan('horizon:doctor')->assertExitCode(1);
});

it('exits successfully when only queue documentation warnings are reported', function () {
    config([
        'horizon.environments' => [
            'local' => [
                'supervisor-1' => [
                    'maxProcesses' => 1,
                ],
            ],
        ],
        'horizon.defaults' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'notifications'],
                'balance' => 'auto',
                'timeout' => 60,
                'tries' => 1,
            ],
        ],
        'queue.connections' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
            ],
        ],
    ]);

    $this->artisan('horizon:doctor')->assertExitCode(0);
});

it('fails with strict-warnings when queue documentation warnings are reported', function () {
    config([
        'horizon.environments' => [
            'local' => [
                'supervisor-1' => [
                    'maxProcesses' => 1,
                ],
            ],
        ],
        'horizon.defaults' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'notifications'],
                'balance' => 'auto',
                'timeout' => 60,
                'tries' => 1,
            ],
        ],
        'queue.connections' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
            ],
        ],
    ]);

    $this->artisan('horizon:doctor', ['--strict-warnings' => true])->assertExitCode(1);
});
