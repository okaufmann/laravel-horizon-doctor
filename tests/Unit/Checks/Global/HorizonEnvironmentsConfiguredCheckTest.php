<?php

use Okaufmann\LaravelHorizonDoctor\Checks\Global\HorizonEnvironmentsConfiguredCheck;

it('reports when horizon environments are missing or empty', function () {
    config(['horizon.environments' => null]);

    expect((new HorizonEnvironmentsConfiguredCheck())->check())->not->toBeEmpty();

    config(['horizon.environments' => []]);

    expect((new HorizonEnvironmentsConfiguredCheck())->check())->not->toBeEmpty();
});

it('passes when at least one environment is configured', function () {
    config(['horizon.environments' => ['production' => []]]);

    expect((new HorizonEnvironmentsConfiguredCheck())->check())->toBe([]);
});
