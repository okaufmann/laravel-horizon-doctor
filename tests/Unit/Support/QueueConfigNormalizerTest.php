<?php

use Okaufmann\LaravelHorizonDoctor\Support\QueueConfigNormalizer;

it('treats a missing queue key as the default queue name', function () {
    expect(QueueConfigNormalizer::listedQueueNames([
        'driver' => 'redis',
    ]))->toBe(['default']);
});

it('normalizes a string queue value', function () {
    expect(QueueConfigNormalizer::listedQueueNames([
        'driver' => 'redis',
        'queue' => 'mail',
    ]))->toBe(['mail']);
});

it('flattens array queue values', function () {
    expect(QueueConfigNormalizer::listedQueueNames([
        'driver' => 'redis',
        'queue' => ['a', 'b'],
    ]))->toBe(['a', 'b']);
});

it('uses the connection default when supervisor queue is empty', function () {
    $supervisor = [
        'connection' => 'redis',
        'queue' => [],
    ];

    $connections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['imports'],
        ],
    ];

    expect(QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisor, $connections)->all())->toBe(['imports']);
});

it('uses supervisor queues when non-empty', function () {
    $supervisor = [
        'connection' => 'redis',
        'queue' => ['alpha'],
    ];

    $connections = [
        'redis' => [
            'driver' => 'redis',
            'queue' => ['default'],
        ],
    ];

    expect(QueueConfigNormalizer::effectiveHorizonQueuesForSupervisor($supervisor, $connections)->all())->toBe(['alpha']);
});
