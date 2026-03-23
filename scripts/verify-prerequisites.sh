#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${TARGET_DIR:-}" ]]; then
  echo "::error::TARGET_DIR is not set"
  exit 1
fi

cd "$TARGET_DIR"

if [[ ! -f composer.json ]]; then
  echo "::error title=Missing composer.json::Expected composer.json in ${TARGET_DIR} (check working-directory input)."
  exit 1
fi

if [[ ! -f artisan ]]; then
  echo "::error title=Missing artisan::Expected the Laravel artisan file in ${TARGET_DIR} (check working-directory input)."
  exit 1
fi

min_php="${MINIMUM_PHP_VERSION:-8.4}"
if ! php -r "exit(version_compare(PHP_VERSION, '${min_php}', '>=') ? 0 : 1);"; then
  echo "::error title=Unsupported PHP version::PHP $(php -r 'echo PHP_VERSION;') does not meet minimum ${min_php}. Use actions that install a supported PHP (see okaufmann/laravel-horizon-doctor composer require php)."
  exit 1
fi

if ! grep -q 'okaufmann/laravel-horizon-doctor' composer.json; then
  echo "::error title=Package not required::composer.json must list okaufmann/laravel-horizon-doctor under require or require-dev."
  exit 1
fi

if [[ ! -f vendor/autoload.php ]]; then
  echo "::error title=Missing vendor directory::Run composer install (or deploy vendor) before this action."
  exit 1
fi

if ! composer show okaufmann/laravel-horizon-doctor --no-interaction &>/dev/null; then
  echo "::error title=Package not installed::okaufmann/laravel-horizon-doctor is not installed. Run composer install and ensure the lockfile is committed or updated in CI."
  exit 1
fi

if ! composer show laravel/horizon --no-interaction &>/dev/null; then
  echo "::error title=Laravel Horizon missing::laravel/horizon must be installed; Horizon Doctor validates Horizon against your queue configuration."
  exit 1
fi

if ! php artisan help horizon:doctor --no-interaction &>/dev/null; then
  echo "::error title=horizon:doctor not registered::The horizon:doctor Artisan command was not found. Ensure the package service provider is discovered (default with Laravel package discovery)."
  exit 1
fi

echo "Prerequisite checks passed."
