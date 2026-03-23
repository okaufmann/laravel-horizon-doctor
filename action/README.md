# Laravel Horizon Doctor — GitHub Action

Composite action that verifies your CI job is set up correctly, then runs [`okaufmann/laravel-horizon-doctor`](https://github.com/okaufmann/laravel-horizon-doctor) via `php artisan horizon:doctor`.

## Prerequisites (your workflow)

1. **Checkout** your repository.
2. **Install PHP** (for example [`shivammathur/setup-php`](https://github.com/shivammathur/setup-php)) with a version that satisfies both your app and this package (currently **PHP ≥ 8.4** for the latest release).
3. **Install Composer dependencies** so `vendor/` exists and the `horizon:doctor` command is registered (`composer install` or equivalent).

The action does **not** run `composer install` for you, so you keep control over flags, caching, and authentication.

## Usage

Reference a release tag or commit SHA (recommended for production). Using `@main` is convenient but can pick up breaking changes.

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

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run Horizon Doctor
        uses: okaufmann/laravel-horizon-doctor@v1
        # or: uses: okaufmann/laravel-horizon-doctor@v1.2.3
```

### Monorepo or subdirectory app

If `composer.json` and `artisan` live under a path such as `apps/api`:

```yaml
- uses: okaufmann/laravel-horizon-doctor@v1
  with:
    working-directory: apps/api
```

## Inputs

| Input | Required | Default | Description |
| --- | --- | --- | --- |
| `working-directory` | No | `.` | App root relative to the repository root. |
| `minimum-php-version` | No | `8.4` | Minimum PHP `major.minor`; must be compatible with the doctor package version you use. |
| `skip-prerequisites` | No | `false` | Set to `true` to skip local checks (PHP version, Composer, package install, `horizon:doctor` registration). Doctor still runs and can fail the job. |

## Outputs

| Output | Description |
| --- | --- |
| `result` | `success` or `failure` after `horizon:doctor` finishes (the job still fails with a non-zero exit code when checks fail). |

## What the prerequisite step checks

- `composer.json` and `artisan` exist under `working-directory`.
- PHP version is at least `minimum-php-version`.
- `okaufmann/laravel-horizon-doctor` appears in `composer.json` and is installed (`composer show`).
- `laravel/horizon` is installed.
- `php artisan help horizon:doctor` succeeds (command is registered).

## Publishing on the GitHub Marketplace

This repository ships a root [`action.yml`](../action.yml) with [`branding` metadata](https://docs.github.com/en/actions/creating-actions/metadata-syntax-for-github-actions#branding) for Marketplace listings. To publish:

1. Ensure the default branch contains a valid `action.yml` and this README (Marketplace surfaces the repository README; keep a **Usage** section there).
2. In the GitHub UI: **Actions** → **Publish in Marketplace** (or create a release and follow the “Publish this Action” flow when prompted).
3. Prefer **version tags** (`v1`, `v1.0.0`) so consumers get predictable upgrades.

Semantic versioning: tag releases (e.g. `v1.0.0`) and optionally maintain a moving `v1` tag for the latest 1.x.

## Troubleshooting

- **`vendor/autoload.php` missing** — Add a `composer install` step before the action.
- **`horizon:doctor` not registered** — Confirm `extra.laravel.providers` / package discovery includes the package (default when using Composer’s Laravel plugin).
- **Wrong directory** — Set `working-directory` to the folder that contains both `artisan` and `composer.json`.

## License

Same as the parent repository ([MIT](../LICENSE.md)).
