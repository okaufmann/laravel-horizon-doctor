<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Global;

use Okaufmann\LaravelHorizonDoctor\Checks\Contracts\GlobalCheck;

final class HorizonEnvironmentsConfiguredCheck implements GlobalCheck
{
    public function check(): array
    {
        $environments = config('horizon.environments');

        if (! is_array($environments) || $environments === []) {
            return [
                'No `horizon.environments` are configured in config/horizon.php (or the value is not an array).',
            ];
        }

        return [];
    }
}
