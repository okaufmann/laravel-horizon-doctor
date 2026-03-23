<?php

namespace Okaufmann\LaravelHorizonDoctor\Support;

class HorizonConfigMerger
{
    /**
     * Merge Horizon default supervisor options into an environment's supervisors (same rules as Horizon).
     *
     * @return array<string, array<string, mixed>>
     */
    public function mergeSupervisorsForEnvironment(string $environment): array
    {
        $defaults = config('horizon.defaults', []);
        $supervisors = config("horizon.environments.$environment", []);

        if (! is_array($defaults) || ! is_array($supervisors)) {
            return [];
        }

        foreach ($defaults as $key => $value) {
            if (isset($supervisors[$key]) && is_array($value)) {
                $supervisors[$key] = array_merge($value, $supervisors[$key]);
            }
        }

        return $supervisors;
    }
}
