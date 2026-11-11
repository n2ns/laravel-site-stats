# Contributing

Bug reports and pull requests are welcome. For larger changes, please open an issue first so we
can agree on the approach.

## Setup

You need PHP 8.3 or newer, Composer and Node.js (for the tracking script's tests).

```bash
git clone https://github.com/n2ns/laravel-site-stats.git
cd laravel-site-stats
composer install
npm ci
```

## Checks

Run these before opening a pull request; CI runs the same ones.

```bash
vendor/bin/pest                          # PHP tests (SQLite in memory)
npm test                                 # tracking script tests (node --test + jsdom)
vendor/bin/pint                          # code style (use --test to only check)
vendor/bin/phpstan analyse --memory-limit=1G
```

CI also runs the PHP tests on MySQL and PostgreSQL, with PHP 8.3 and 8.4 and Laravel 12 and 13.

## Pull requests

- Add or update tests for every change in behaviour.
- Update `README.md`, `PRIVACY.md` and the `Unreleased` section of `CHANGELOG.md` when the change
  is visible to users. Anything that changes what is collected or stored must be reflected in
  `PRIVACY.md`.
- Keep the tracking script free of dependencies, cookies and browser storage.

By contributing you agree that your contribution is licensed under the
[Apache License 2.0](LICENSE).
