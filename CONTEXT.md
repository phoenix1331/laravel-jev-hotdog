# Context

Public Laravel 13 playground (`github.com/phoenix1331/laravel-jev-hotdog`): drop a photo, get HOTDOG / NOT HOTDOG / NOT SURE, with a box drawn around the hot dog. Showcases Jev (TypeSafe) image input with Noul and Box question types. Learning repo, not a package: no Packagist, tags, releases or CHANGELOG; `main` is current.

## Stack

| Area | Choice |
|------|--------|
| Framework | Laravel 13, PHP 8.3+ (developed on 8.4) |
| Runtime | Native WSL, `php artisan dev` locally; `php artisan serve` or Herd for users |
| Storage | None. Session `file`, cache `file`, queue `sync`, failed jobs `null`; only the `local` disk (Livewire temp) and `s3` remain in `config/filesystems.php` |
| Frontend | Livewire 3.8 (`WithFileUploads`; brief pins v3 although v4 exists), Tailwind 4 via `@tailwindcss/vite`, Vite 8 |
| Quality | Pest 4 (+ Laravel, Livewire plugins), Larastan level 8 on `app/`, `config/`, `routes/` (`phpstan.neon`), arch tests in `tests/Unit/ArchTest.php` (no debug helpers, no `env()` outside config, strict types), Pint (`pint.json`: `laravel` preset + `declare_strict_types`, applied to every PHP file), Husky hooks, pcov for coverage (90% min on `app/`) |
| Git hooks | Husky 9 (`.husky/`, tracked): `commit-msg` enforces lowercase conventional commits, `pre-commit` runs Pint and `osv-scanner` on `composer.lock`; installed by `npm install` via `prepare` |
| CI | GitHub Actions, fake driver only, no secrets |

## Deviations from the default 1331 stack

| Default | Here | Why |
|---------|------|-----|
| Docker + FrankenPHP | Native PHP | Clone-and-run in minutes with `composer install`; no containers to learn |
| MySQL | No database | Nothing is stored; `database/`, `User` model, `config/auth.php` and the `public` disk removed |
| Makefile | Composer scripts (`composer.json` `scripts`) | Brief forbids a Makefile; `composer` is already required |
| pcov in Dockerfile | pcov on host (`php8.4-pcov`) and in CI | No Dockerfile |

## Commands

**composer**: dev | test | coverage (`--min=90`) | lint | lint:check | analyse

Composer 2.8+ required: the scripts use the `@no_additional_args` token (Laravel 13 skeleton default).

## Configuration

| File | Keys |
|------|------|
| `config/jev.php` | `api_key`, `model` (`jev-preview`), `base_url`, `fake` (default `true`), `timeout` |
| `config/hotdog.php` | `unsure_min` / `unsure_max` (0.40 / 0.60), `max_upload_kb` (20480), `rate_limit_per_minute` (20) |

`env()` is only read in `config/`. `phpunit.xml` forces `JEV_FAKE=true` and an empty key.

## Design decisions

- **Noul then Box** - Box always returns a box even when nothing is there, so Noul decides the verdict; Box runs only when confidence is above `unsure_max`. Box edges are 0 to 1 fractions, so the overlay is plain CSS percentages
- **Not sure band** - confidence between `unsure_min` and `unsure_max` shows a third state; confidence always shown
- **Normalise before sending** - Jev accepts only still PNG/JPEG, 256 to 1568px per side, max 16 MiB, and rejects rather than resizes; every upload is converted and resized first
- **Preview API** - every image request sends `TypeSafe-Danger-Zone-Images: true`; Box needs `jev-1.14.0` or `jev-preview`, so the model is pinned in config
- **Bring your own key** - no key in the repo, tests, fixtures or recordings; fake mode is the default so the UI works straight after cloning
- **Nothing stored** - uploads live in Livewire temp and are deleted after the result; logs hold only verdict, confidence and latency
- **No show branding** - no HBO name or logo; one credit line in the README

## Planned components

| Component | Role |
|-----------|------|
| `HotdogDetector` contract | `detect(NormalisedImage): HotdogResult`; bound from `jev.fake` |
| `JevHotdogDetector` / `FakeHotdogDetector` | Real API vs deterministic local answers (from `samples/expected.json` or image hash) |
| `ImageNormaliser` | Intervention Image v3, GD default, Imagick for HEIC |
| `HotdogUploader` | Single Livewire component: upload, validation, rate limit, result |
| `hotdog:check` / `hotdog:eval` | Key/model/header check; golden-set accuracy over `samples/` |

## Host requirements

PHP extensions: `gd` (required for image normalisation), `imagick` (optional, HEIC), `pcov` (coverage).
