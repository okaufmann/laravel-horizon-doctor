<?php

// config for Okaufmann/LaravelHorizonDoctor
return [

    /*
    | When true, `horizon:doctor` exits with a non-zero code if any warnings are reported
    | (for example queue names Horizon runs but `config/queue.php` does not list under the
    | same connection). CLI: use `--strict-warnings` to enable for a single run.
    */
    'strict_warnings' => false,

    /*
    | When false, skip printing the per-environment Redis queue overview table (same as --no-overview).
    */
    'show_overview' => true,

    /*
    | When true, print passing supervisors, the full overview table (including OK rows), section blurbs,
    | and long explanations on queue checks. CLI: use -v / -vv instead of this when you want detail once.
    */
    'verbose' => false,

];
