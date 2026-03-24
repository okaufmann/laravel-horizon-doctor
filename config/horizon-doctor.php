<?php

// config for Okaufmann/LaravelHorizonDoctor
return [

    /*
    | When true, `horizon:doctor` exits with a non-zero code if any warnings are reported
    | (for example queue names Horizon runs but `config/queue.php` does not list under the
    | same connection). CLI: use `--strict-warnings` to enable for a single run.
    */
    'strict_warnings' => env('HORIZON_DOCTOR_STRICT_WARNINGS', false),

    /*
    | When false, skip printing the per-environment Redis queue overview table (same as --no-overview).
    */
    'show_overview' => env('HORIZON_DOCTOR_SHOW_OVERVIEW', true),

    /*
    | When true, print passing supervisors, the full overview table (including OK rows), section blurbs,
    | and long explanations on queue checks without needing `-v`. CLI `-v` / `-vv` still enables verbose too.
    */
    'verbose' => false,

    /*
    | When true, scan PHP classes under `queued_class_paths` for queued (ShouldQueue) issues.
    | CLI: `--scan-jobs` / `--no-scan-jobs` override this for a single run.
    */
    'scan_queued_classes' => env('HORIZON_DOCTOR_SCAN_QUEUED_CLASSES', false),

    /*
    | Directories relative to the application base path (used when scanning is enabled).
    */
    'queued_class_paths' => [
        'app/Jobs',
        'app/Listeners',
        'app/Mail',
    ],

    /*
    | Optional regex patterns passed to Symfony Finder::notPath() (e.g. '#^tests/#') to skip files.
    */
    'queued_class_exclude_patterns' => [],

    /*
    | When true, job timeouts that violate Redis `retry_after` on the scanned connection are errors;
    | when false, they are warnings only.
    | CLI: `--strict-job-timeouts` / `--no-strict-job-timeouts` override for a single run when scanning.
    */
    'strict_job_timeouts' => env('HORIZON_DOCTOR_STRICT_JOB_TIMEOUTS', true),

];
