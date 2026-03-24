# Laravel Horizon Doctor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/okaufmann/laravel-horizon-doctor.svg?style=flat-square)](https://packagist.org/packages/okaufmann/laravel-horizon-doctor)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/okaufmann/laravel-horizon-doctor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/okaufmann/laravel-horizon-doctor/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/okaufmann/laravel-horizon-doctor/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/okaufmann/laravel-horizon-doctor/actions/workflows/fix-php-code-style-issues.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/okaufmann/laravel-horizon-doctor.svg?style=flat-square)](https://packagist.org/packages/okaufmann/laravel-horizon-doctor)

Checks your Horizon config against the Laravel queue config to ensure everything is configured as expected. Optionally scans queued job/listener/mail classes for common footguns and cross-checks their queues against Horizon.

## Installation

### Requirements

- PHP 8.4+
- Laravel 12 or 13 (`illuminate/support` is pulled in by your app)
- [Laravel Horizon](https://laravel.com/docs/horizon) (`laravel/horizon`)

### Install

Require the package with [Composer](https://getcomposer.org/):

```bash
composer require okaufmann/laravel-horizon-doctor
```

The service provider and `LaravelHorizonDoctor` facade are [auto-discovered](https://laravel.com/docs/packages#package-discovery); you do not need to register them manually.

### Publish configuration (optional)

You do not have to publish anything; the package merges its defaults. When you want to change options, run:

```bash
php artisan vendor:publish --tag=horizon-doctor-config
```

That adds `config/horizon-doctor.php` to your app.

## Usage

```bash
php artisan horizon:doctor
```

Errors block the exit code by default. Some **warnings** (for example Horizon supervises a queue that is not listed under the same connection in `config/queue.php`) print as warnings only. Use `php artisan horizon:doctor --strict-warnings` or `strict_warnings` in `config/horizon-doctor.php` to fail on those as well. The [GitHub Action](action/README.md) exposes the same behavior via the `strict-warnings` input.

Each Horizon environment prints a **Redis queue overview table** (queue name × connection × whether it appears in `config/queue.php` × which supervisors run it × a short status). Use `--no-overview` or `show_overview` => `false` in `config/horizon-doctor.php` to hide it (for example in compact CI logs).

For a compact reference when writing queueable jobs and listeners, see [docs/jobs-horizon-cheatsheet.md](docs/jobs-horizon-cheatsheet.md).

### Queued class scan (optional)

Static analysis of PHP under configurable directories (default: `app/Jobs`, `app/Listeners`, `app/Mail`). **Disabled by default** so config-only runs stay quick and do not depend on your sources being present or parseable.

- **Turn on:** `php artisan horizon:doctor --scan-jobs`, or `scan_queued_classes` => `true` in published `config/horizon-doctor.php`.
- **Skip once:** `--no-scan-jobs` overrides config.
- **Paths / ignores:** `queued_class_paths` (relative to the app base path), `queued_class_exclude_patterns` (regexes for Symfony Finder `notPath`).

Checks performed when scanning:

- **Job `timeout` vs Redis `retry_after`** for the effective Redis connection (literal `$connection` on the class when present, otherwise `queue.default`) — `strict_job_timeouts` chooses error vs warning when the timeout is not safely below `retry_after`.
- **Queued listeners** — warns if `onQueue()` is only used from `__construct()`; prefer `#[Queue]`, `public $queue`, or the queue at dispatch time.
- **Horizon vs declared queue** — after a scan, each environment section can warn if a class targets a Redis queue/connection that no supervisor handles there.

The published composite action can enable scanning with the `scan-queued-classes` and `strict-job-timeouts` inputs (see [action/README.md](action/README.md)); configure paths and exclude patterns in published `config/horizon-doctor.php`, or run `php artisan horizon:doctor --scan-jobs` in a custom step.

## GitHub Action

Run the same checks in CI with the [composite action](https://docs.github.com/en/actions/creating-actions/creating-a-composite-action) published from this repository:

```yaml
jobs:
  horizon-doctor:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.4"
          tools: composer:v2
      - run: composer install --no-interaction --prefer-dist
      - uses: okaufmann/laravel-horizon-doctor@v1
```

Pin a **semver tag** (for example `v1.0.0`) instead of `@v1` if you want fully reproducible builds. Full inputs, outputs, prerequisite behavior, and Marketplace notes are documented in [action/README.md](action/README.md).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Oliver Kaufmann](https://github.com/okaufmann)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
