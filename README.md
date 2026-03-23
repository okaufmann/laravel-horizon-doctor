# Laravel Horizon Doctor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/okaufmann/laravel-horizon-doctor.svg?style=flat-square)](https://packagist.org/packages/okaufmann/laravel-horizon-doctor)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/okaufmann/laravel-horizon-doctor/run-tests?label=tests)](https://github.com/okaufmann/laravel-horizon-doctor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/okaufmann/laravel-horizon-doctor/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/okaufmann/laravel-horizon-doctor/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/okaufmann/laravel-horizon-doctor.svg?style=flat-square)](https://packagist.org/packages/okaufmann/laravel-horizon-doctor)

Checks your Horizon config against the Laravel queue config to ensure everything is configured as expected.

## Installation

You can install the package via composer:

```bash
composer require okaufmann/laravel-horizon-doctor
```

## Usage

```bash
php artisan horizon:doctor
```

Errors block the exit code by default. Some **warnings** (for example Horizon supervises a queue that is not listed under the same connection in `config/queue.php`) print as warnings only. Use `php artisan horizon:doctor --strict-warnings` or `strict_warnings` in `config/horizon-doctor.php` to fail on those as well. The [GitHub Action](action/README.md) exposes the same behavior via the `strict-warnings` input.

Each Horizon environment prints a **Redis queue overview table** (queue name × connection × whether it appears in `config/queue.php` × which supervisors run it × a short status). Use `--no-overview` or `show_overview` => `false` in `config/horizon-doctor.php` to hide it (for example in compact CI logs).

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
