# Changelog

All notable changes to `laravel-horizon-doctor` will be documented in this file.

## v2.1.0 - 2026-03-23

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v2.0.0...v2.1.0

## v2.0.0 - 2026-03-23

### Release highlights

#### Breaking changes

- **PHP 8.4+** is required (`^8.4`).
- **Laravel 12 and 13 only** (`illuminate/*` `^12.0|^13.0`); older Laravel versions are no longer supported.
- **`horizon:doctor` exit code**: the command now exits with a **non-zero status** when any check reports a problem. This is what you want for CI and scripts that rely on exit codes; anything that assumed the command always exited `0` must be updated.

#### Added

- **GitHub Action** (composite): root `action.yml` runs prerequisite checks, then `php artisan horizon:doctor`. See `action/README.md` for inputs, outputs, and Marketplace notes.
- **Dynamic Horizon environment configuration** ([#15](https://github.com/okaufmann/laravel-horizon-doctor/pull/15)) — thanks @stelles.

#### Changed

- Internal **check architecture** refactored into dedicated check classes (same Artisan entrypoint, clearer structure for maintenance and extension).
- **CI** updated to current GitHub Actions (e.g. `actions/checkout` v4, Pint action bumps, Dependabot metadata action updates).


---

### What's changed

* Bump aglipanci/laravel-pint-action from 2.1.0 to 2.2.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/3
* Bump dependabot/fetch-metadata from 1.3.6 to 1.4.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/4
* Bump dependabot/fetch-metadata from 1.4.0 to 1.5.1 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/5
* Bump aglipanci/laravel-pint-action from 2.2.0 to 2.3.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/6
* Bump dependabot/fetch-metadata from 1.5.1 to 1.6.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/7
* Bump actions/checkout from 3 to 4 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/8
* Bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/9
* Bump aglipanci/laravel-pint-action from 2.3.0 to 2.3.1 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/10
* Bump aglipanci/laravel-pint-action from 2.3.1 to 2.4 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/12
* Added dynamic horizon config environment by @stelles in https://github.com/okaufmann/laravel-horizon-doctor/pull/15
* Bump dependabot/fetch-metadata from 1.6.0 to 2.2.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-horizon-doctor/pull/14

### New contributors

* @stelles made their first contribution in https://github.com/okaufmann/laravel-horizon-doctor/pull/15

**Full changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v1.1.1...v2.0.0

## v1.1.1 - 2023-02-23

### Changed

- handle arrays as queue config

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v1.1.0...v1.1.1

## v1.1.0 - 2023-02-15

### What's Changed

- Bump dependabot/fetch-metadata from 1.3.5 to 1.3.6 by @dependabot in https://github.com/okaufmann/laravel-horizon-doctor/pull/2
- Added Laravel 10 support

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v1.0.0...v1.1.0

## v1.0.0 - 2023-01-26

### What's Changed

- Bump aglipanci/laravel-pint-action from 1.0.0 to 2.1.0 by @dependabot in https://github.com/okaufmann/laravel-horizon-doctor/pull/1

### New Contributors

- @dependabot made their first contribution in https://github.com/okaufmann/laravel-horizon-doctor/pull/1

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.7...v1.0.0

## v0.0.7 - 2022-11-08

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.6...v0.0.7

## v0.0.6 - 2022-11-08

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.5...v0.0.6

## v0.0.5 - 2022-11-08

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.4...v0.0.5

## v0.0.4 - 2022-11-08

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.3...v0.0.4

## v0.0.3 - 2022-11-08

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.2...v0.0.3

## v0.0.2 - 2022-11-08

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/compare/v0.0.1...v0.0.2

## v0.0.1 - 2022-11-07

**Full Changelog**: https://github.com/okaufmann/laravel-horizon-doctor/commits/v0.0.1
