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

it('runs with --scan-jobs when no queued classes are found under default paths', function () {
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

    $this->artisan('horizon:doctor', ['--scan-jobs' => true])->assertExitCode(0);
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

    $this->artisan('horizon:doctor')
        ->expectsOutputToContain('No errors found (see warnings above).')
        ->assertExitCode(0);
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

it('keeps compact output when HORIZON_DOCTOR_VERBOSE is false even if config verbose is true and output is verbose', function () {
    try {
        putenv('HORIZON_DOCTOR_VERBOSE=false');
        $_ENV['HORIZON_DOCTOR_VERBOSE'] = 'false';

        config([
            'horizon-doctor.verbose' => true,
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
        expect($out->fetch())->not->toContain('Redis queue overview');
    } finally {
        putenv('HORIZON_DOCTOR_VERBOSE');
        unset($_ENV['HORIZON_DOCTOR_VERBOSE']);
    }
});

it('prints the full Redis queue overview when HORIZON_DOCTOR_VERBOSE is true without raising output verbosity', function () {
    try {
        putenv('HORIZON_DOCTOR_VERBOSE=true');
        $_ENV['HORIZON_DOCTOR_VERBOSE'] = 'true';

        config([
            'horizon-doctor.verbose' => false,
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
        expect($out->fetch())->toContain('Redis queue overview');
    } finally {
        putenv('HORIZON_DOCTOR_VERBOSE');
        unset($_ENV['HORIZON_DOCTOR_VERBOSE']);
    }
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
    expect($text)->toContain('No errors found.');
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
