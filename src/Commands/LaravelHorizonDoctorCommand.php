<?php

namespace Okaufmann\LaravelHorizonDoctor\Commands;

use Illuminate\Console\Command;
use Okaufmann\LaravelHorizonDoctor\HorizonDoctorRunner;

class LaravelHorizonDoctorCommand extends Command
{
    public $signature = 'horizon:doctor
                            {--strict-warnings : Treat documentation/consistency warnings as failures (non-zero exit)}
                            {--no-overview : Hide the Redis queue vs Horizon mapping table}
                            {--scan-jobs : Scan queued classes (app/Jobs, Listeners, Mail, …) for timeout/queue footguns}
                            {--no-scan-jobs : Skip queued-class scan even if enabled in config}';

    public $description = 'Checks your Horizon config against the Laravel queue config to ensure everything is configured as expected. Use -v for full output (all rows, passing checks, long hints).';

    public function handle(HorizonDoctorRunner $runner): int
    {
        return $runner->run($this);
    }
}
