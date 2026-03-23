<?php

use Okaufmann\LaravelHorizonDoctor\Support\RedisQueueHorizonOverview;

it('marks rows OK when queue.php and horizon agree on a connection', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['default', 'mail'],
        ],
    ];

    $supervisors = [
        'worker' => [
            'connection' => 'redis',
            'queue' => ['default', 'mail'],
        ],
    ];

    $rows = RedisQueueHorizonOverview::rows($supervisors, $queueConnections);

    expect($rows)->toHaveCount(2);
    expect(collect($rows)->pluck('status')->unique()->all())->toBe(['OK']);
});

it('flags a queue listed on the wrong redis connection when horizon uses another', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['document-generation'],
        ],
        'redis-long-running' => [
            'driver' => 'redis',
            'queue' => ['participant-imports'],
        ],
    ];

    $supervisors = [
        'long' => [
            'connection' => 'redis-long-running',
            'queue' => ['document-generation', 'participant-imports'],
        ],
    ];

    $rows = RedisQueueHorizonOverview::rows($supervisors, $queueConnections);

    $wrong = collect($rows)->firstWhere(fn (array $r) => $r['connection'] === 'redis' && $r['queue'] === 'document-generation');
    expect($wrong['status'])->toContain('redis-long-running');
    expect($wrong['status'])->toStartWith('Error:');

    $docOnLong = collect($rows)->firstWhere(fn (array $r) => $r['connection'] === 'redis-long-running' && $r['queue'] === 'document-generation');
    expect($docOnLong['status'])->toStartWith('Warning:');

    $imports = collect($rows)->firstWhere(fn (array $r) => $r['connection'] === 'redis-long-running' && $r['queue'] === 'participant-imports');
    expect($imports['status'])->toBe('OK');
});

it('warns when horizon runs a queue not listed on that connection in queue.php', function () {
    $queueConnections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => 'default',
        ],
    ];

    $supervisors = [
        'worker' => [
            'connection' => 'redis',
            'queue' => ['default', 'notifications'],
        ],
    ];

    $row = collect(RedisQueueHorizonOverview::rows($supervisors, $queueConnections))
        ->firstWhere('queue', 'notifications');

    expect($row['queue_php'])->toBe('no');
    expect($row['status'])->toStartWith('Warning:');
});

it('ignores non-redis horizon connections in the overview', function () {
    $queueConnections = [
        'redis' => ['driver' => 'redis', 'queue' => 'default'],
        'sync' => ['driver' => 'sync', 'queue' => 'default'],
    ];

    $supervisors = [
        'sync-worker' => ['connection' => 'sync', 'queue' => ['default']],
    ];

    $rows = RedisQueueHorizonOverview::rows($supervisors, $queueConnections);

    expect(collect($rows)->pluck('connection')->unique()->all())->toBe(['redis']);
});
