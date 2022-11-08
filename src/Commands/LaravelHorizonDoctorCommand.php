<?php

namespace Okaufmann\LaravelHorizonDoctor\Commands;

use Illuminate\Console\Command;

class LaravelHorizonDoctorCommand extends Command
{
    public $signature = 'horizon:doctor';

    public $description = 'Checks your Horizon config against the Laravel queue config to ensure everything is configured as expected.';

    public function handle(): int
    {
        $this->info('Checking your Horizon configs...');

        $horizonConfigs = config('horizon.environments.production');
        $default = config('horizon.defaults');
        $queueConfigs = config('queue.connections');

        foreach ($default as $key => $value) {
            if (isset($horizonConfigs[$key])) {
                $horizonConfigs[$key] = [
                    ...$value,
                    ...$horizonConfigs[$key],
                ];
            }
        }

        foreach ($horizonConfigs as $key => $horizonConfig) {
            $errors = collect();
            $this->info("Checking queue `{$key}`");

            // check if connection of horizon queue is configured in queue config
            if (! ($queueConfigs[$horizonConfig['connection']] ?? false)) {
                $errors[] = "Connection {$horizonConfig['connection']} not found for `{$key}` in config/queue.php";
            }

            $queueConnection = $queueConfigs[$horizonConfig['connection']];

            // check that queue of the connection is set in horizon
            if (! in_array($queueConnection['queue'], $horizonConfig['queue'], true)) {
                $errors[] = "Queue `{$queueConnection['queue']}` should be added to the `{$key}['queue']` array in config/horizon.php";
            }

            // check that horizon queue has a timout option
            if (! isset($horizonConfig['timeout'])) {
                $errors[] = "You should consider setting the `timeout` option for `{$key}` in config/horizon.php";
            }

            // check that timeout is lower than retry_after
            if (isset($horizonConfig['timeout']) && $horizonConfig['timeout'] >= $queueConnection['retry_after']) {
                $errors[] = "`timeout` of configured horizon queue `{$key}` ({$horizonConfig['timeout']}) in config/horizon.php should be marginally bigger than the `retry_after` option of the queue connection `{$key}` ({$horizonConfig['timeout']}) set in config/queue.php";
            }
            if ($errors->count()) {
                $errors->each(fn ($error) => $this->error("- {$error}"));
            } else {
                $this->info('- Everything looks good!');
            }

            $this->comment('');
        }

        $this->info('Running some global checks...');

        // check that all queue connections are used in horizon
        $redisQueues = collect($queueConfigs)
            ->filter(fn ($queue) => $queue['driver'] === 'redis')
            ->keys();
        $usedConnectionsInHorizon = collect($horizonConfigs)
            ->map(fn ($queue) => $queue['connection'])
            ->unique()
            ->values();

        $diff = $redisQueues->diff($usedConnectionsInHorizon);

        if (count($diff) > 0) {
            $diff = $diff->implode(',');
            $this->error("- You should consider configuring the following queues in config/horizon.php: {$diff}");
        } else {
            $this->info('- Everything looks good!');
        }

        return self::SUCCESS;
    }
}
