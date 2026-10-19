# Context

Public Laravel 13 playground (`github.com/phoenix1331/laravel-jev-hotdog`): drop a photo, get HOTDOG / NOT HOTDOG / NOT SURE, with a box drawn around the hot dog. Showcases Jev (TypeSafe) image input with Noul and Box question types. Learning repo, not a package: no Packagist, tags, releases or CHANGELOG; `main` is current.

## Stack

| Area | Choice |
|------|--------|
| Framework | Laravel 13, PHP 8.3+ (developed on 8.4); `composer.json` pins `config.platform.php` to 8.3.0 so the lock resolves Symfony 7.4 LTS rather than Symfony 8 (PHP 8.4+ only) |
| Runtime | Native WSL, `php artisan dev` locally; `php artisan serve` or Herd for users |
| Storage | None. Session `file`, cache `file`, queue `sync`, failed jobs `null`; only the `local` disk (Livewire temp) and `s3` remain in `config/filesystems.php` |
| Frontend | Livewire 3.8 (`WithFileUploads`; brief pins v3 although v4 exists), Tailwind 4 via `@tailwindcss/vite`, Vite 8 |
| Quality | Pest 4 (+ Laravel, Livewire plugins), Larastan level 8 on `app/`, `config/`, `routes/` (`phpstan.neon`), arch tests in `tests/Unit/ArchTest.php` (no debug helpers, no `env()` outside config, strict types), Pint (`pint.json`: `laravel` preset + `declare_strict_types`, applied to every PHP file), Husky hooks, pcov for coverage (90% min on `app/`) |
| Git hooks | Husky 9 (`.husky/`, tracked): `commit-msg` enforces lowercase conventional commits, `pre-commit` runs Pint and `osv-scanner` on `composer.lock`; installed by `npm install` via `prepare` |
| CI | `.github/workflows/ci.yml`: `quality` (Pint, Larastan), `tests` (PHP 8.3 and 8.4 matrix), `coverage` (pcov, `--min=90`, `shell: bash` so `tee` keeps pipefail); `JEV_FAKE=true`, no secrets |

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
| `config/hotdog.php` | `unsure_min` / `unsure_max` (0.40 / 0.60), `max_upload_kb` (20480), `rate_limit_per_minute` (20), `samples_path` (`base_path('samples')`) |

`env()` is only read in `config/`. `phpunit.xml` forces `JEV_FAKE=true` and an empty key.

## Design decisions

- **Noul decides, Box locates, one request** - Box always returns a box even when nothing is there, so the Noul answer decides the verdict and the box is shown only above `unsure_max`. Both questions go in one request (the pattern TypeSafe documents): the image is billed as input tokens on every request and output is free, so a second Box call would bill the image twice and add a round trip. Box edges are 0 to 1 fractions, so the overlay is plain CSS percentages
- **Not sure band** - confidence between `unsure_min` and `unsure_max` shows a third state; confidence always shown
- **Normalise before sending** - Jev accepts only still PNG/JPEG, 280 to 1568px per side (the docs say 280, not the 256 first assumed), 78,400 to 2,458,624 total pixels, max 16 MiB, and rejects rather than resizes. It also strips EXIF without applying orientation. Every upload is rotated upright, resized and re-encoded as baseline JPEG first
- **Preview API** - every image request sends `TypeSafe-Danger-Zone-Images: true`; images and Box need Jev 1.14 (`jev-preview` or `jev-1.14.0`; `jev-latest` is still 1.13, text only), so the model is pinned in config
- **Bring your own key** - no key in the repo, tests, fixtures or recordings; fake mode is the default so the UI works straight after cloning
- **Nothing stored** - uploads live in Livewire temp and are deleted after the result; logs hold only verdict, confidence and latency
- **No show branding** - no HBO name or logo; one credit line in the README

## Jev client

Thin `JevClient` on Laravel's HTTP client, not a Packagist SDK. Reviewed packages:

| Package | Images + Box | Notes |
|---------|--------------|-------|
| `phox/typesafe-sdk-php` | Yes (since v0.5.0) | Only candidate with support; single author, v0.x, three releases in three days; own Guzzle transport bypasses `Http::fake()`; static image registry |
| `doekos/typesafe-laravel-sdk`, `butochnikov/laravel-typesafe-jev` (+ `butochnikov/typesafe-sdk-php`), `f-lombardo/jev-php`, `mzainzulifqar/jev-php-sdk`, `binnash/typesafe-sdk`, `sanmai/typesafe-ai-php`, `symfony/ai-type-safe-platform` | No | Text questions only |

The app needs one endpoint, two question types and a base64 JSON body, which is a small class; owning it keeps `Http::fake()` tests and the preview header explicit. Exact request, response, limits and error codes: `.claude/docs/jev-api.md`. Recorded in the README later.

## Components

Domain lives in `app/Hotdog/`; bindings in `app/Providers/HotdogServiceProvider.php`.

| Component | Status | Role |
|-----------|--------|------|
| `Verdict` enum | Built | `hotdog` / `not_hotdog` / `unsure` (same values as `expected.json`), `label()` for the banner |
| `ConfidenceBand` | Built | Noul probability to verdict; both bounds are inside the not sure band (0.60 exactly is NOT SURE). Singleton from `hotdog.unsure_min/max` |
| `BoundingBox` | Built | Validated 0 to 1 edges; `toCssPercentages()` for the overlay, `toPixels()` for any image size |
| `NormalisedImage` | Built | In-memory bytes, mime, normalised and original dimensions, original filename; `base64()`, `format()` for the Jev asset |
| `HotdogResult` | Built | `decide()` maps confidence through the band and drops the box unless HOTDOG |
| `HotdogDetector` contract | Built | `detect(NormalisedImage): HotdogResult`; resolved from `jev.fake` |
| `FakeHotdogDetector` | Built | `samples/expected.json` match by basename (tricky entries land nearer the band but stay correct), else a SHA-1 of the bytes drives confidence, box and latency; model `fake` |
| `JevClient` / `JevHotdogDetector` | Planned | `POST {base_url}/systemone` with one Noul + Box request via `Http`; until built, `JEV_FAKE=false` throws a `LogicException` |
| `ImageNormaliser` | Planned | Intervention Image v3, GD default, Imagick for HEIC |
| `HotdogUploader` | Planned | Single Livewire component: upload, validation, rate limit, result |
| `hotdog:check` / `hotdog:eval` | Planned | Key/model/header check; golden-set accuracy over `hotdog.samples_path` |

## Host requirements

PHP extensions: `gd` (required for image normalisation), `imagick` (optional, HEIC), `pcov` (coverage).
