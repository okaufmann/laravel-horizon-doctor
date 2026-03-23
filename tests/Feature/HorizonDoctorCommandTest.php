<?php

use Illuminate\Support\Facades\Artisan;
use Okaufmann\LaravelHorizonDoctor\HorizonDoctorRunner;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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

it('prints the full Redis queue overview when using -v and everything matches', function () {
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

    $out = new BufferedOutput();
    $out->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
    Artisan::call('horizon:doctor', [], $out);
    expect($out->fetch())->toContain('Redis queue overview');
});

it('omits the overview table when every queue row is OK', function () {
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

    $out = new BufferedOutput();
    Artisan::call('horizon:doctor', [], $out);
    $text = $out->fetch();
    expect($text)->not->toContain('Redis queue overview');
    expect($text)->toContain('Environment: local');
});

it('hides the overview table with --no-overview', function () {
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

    Artisan::call('horizon:doctor', ['--no-overview' => true]);
    expect(Artisan::output())->not->toContain('Redis queue overview');
});
