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

[//]: # (You can publish the config file with:)

[//]: # ()
[//]: # (```bash)

[//]: # (php artisan vendor:publish --tag="laravel-horizon-doctor-config")

[//]: # (```)

[//]: # (This is the contents of the published config file:)

[//]: # ()
[//]: # (```php)

[//]: # (return [)

[//]: # (];)

[//]: # (```)

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-horizon-doctor-views"
```

## Usage

```bash
php artisan horizon:doctor
```

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
