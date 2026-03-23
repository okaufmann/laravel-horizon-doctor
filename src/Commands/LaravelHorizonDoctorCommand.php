<?php

namespace Okaufmann\LaravelHorizonDoctor\Commands;

use Illuminate\Console\Command;

class LaravelHorizonDoctorCommand extends Command
{
    public $signature = 'horizon:doctor';

    public $description = 'Checks your Horizon config against the Laravel queue config to ensure everything is configured as expected.';

    public function handle(): int
    {
        $environments = config('horizon.environments');
    
        foreach (array_keys($environments) as $env) {
            $this->info("Checking env `{$env}`");
            $this->checkEnvironment($env);
        }
    
        return self::SUCCESS;
    }

    protected function checkEnvironment($env)
    {
        $horizonConfigs = config("horizon.environments.$env");
        $default = config('horizon.defaults');
        $queueConfigs = config('queue.connections');

        foreach ($default as $key => $value) {
            if (isset($horizonConfigs[$key])) {
                $horizonConfigs[$key] = array_merge($value, $horizonConfigs[$key]);
            }
        }

        $this->checkHorizonConfigs($horizonConfigs, $queueConfigs);
        $this->checkDefaultQueuesAreProcessed($horizonConfigs, $queueConfigs);
        $this->checkConnectionsAreUsedInHorizon($queueConfigs, $horizonConfigs);

        return self::SUCCESS;
    }
        

    protected function checkHorizonConfigs(array $horizonConfigs, array $queueConfigs)
    {
        $this->info('Checking your Horizon configs...');

        foreach ($horizonConfigs as $key => $horizonConfig) {
            $errors = collect();
            $this->info("Checking queue `{$key}`");

            // check if connection of horizon queue is configured in queue config
            if (! ($queueConfigs[$horizonConfig['connection']] ?? false)) {
                $errors[] = "Connection {$horizonConfig['connection']} of Horizon worker `{$key}` does not exist in config/queue.php";
            }

            // check that horizon queue has a timout option
            if (! isset($horizonConfig['timeout'])) {
                $errors[] = "You should consider setting the `timeout` option for `{$key}` in config/horizon.php";
            }

            // check that timeout is lower than retry_after
            $queueConnection = $queueConfigs[$horizonConfig['connection']] ?? null;
            if ($queueConnection && isset($horizonConfig['timeout']) && $horizonConfig['timeout'] >= $queueConnection['retry_after']) {
                $errors[] = "`timeout` of configured Horizon queue `{$key}` ({$horizonConfig['timeout']}) in config/horizon.php should be marginally bigger than the `retry_after` option of the queue connection `{$key}` ({$queueConnection['retry_after']}) set in config/queue.php";
            }

            if ($errors->count()) {
                $errors->each(fn ($error) => $this->error("- {$error}"));
            } else {
                $this->info('- Everything looks good!');
            }

            $this->comment('');
        }
    }

    protected function checkConnectionsAreUsedInHorizon(array $queueConfigs, array $horizonConfigs): void
    {
        $this->info('Running some global checks...');

        // check that all queue connections are used in horizon
        $redisQueueConnections = collect($queueConfigs)
            ->filter(fn ($queue) => $queue['driver'] === 'redis')
            ->keys();
        $usedConnectionsInHorizon = collect($horizonConfigs)
            ->map(fn ($queue) => $queue['connection'])
            ->unique()
            ->values();

        $diff = $redisQueueConnections->diff($usedConnectionsInHorizon);

        if (count($diff) > 0) {
            $diff = $diff->implode(',');
            $this->error("- You should consider configuring the following queues in config/horizon.php: {$diff}");
        } else {
            $this->info('- Everything looks good!');
        }
    }

    protected function checkDefaultQueuesAreProcessed(array $horizonConfigs, array $queueConfigs)
    {
        $this->info('Checking your Queue configs...');

        $redisQueueConnections = collect($queueConfigs)
            ->filter(fn ($queue) => $queue['driver'] === 'redis');

        foreach ($redisQueueConnections as $connectionName => $queueConfig) {
            $this->info("Checking connection `{$connectionName}`");
            $errors = collect();

            // check that default queue is processed by horizon
            $processedQueuesInHorizon = collect($horizonConfigs)
                ->map(fn ($queue) => $queue['queue'])
                ->flatten()
                ->unique()
                ->values();

            $handledQueues = collect($queueConfig['queue']);
            foreach ($handledQueues as $queue) {
                if (! $processedQueuesInHorizon->contains($queue)) {
                    $errors[] = "Queue `{$queue}` of connection `{$connectionName}` will not be processed by any worker set in config/horizon.php";
                }
            }

            if ($errors->count()) {
                $errors->each(fn ($error) => $this->error("- {$error}"));
            } else {
                $this->info('- Everything looks good!');
            }

            $this->comment('');
        }
    }
}
