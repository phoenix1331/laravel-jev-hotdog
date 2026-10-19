# Context

Public Laravel 13 playground (`github.com/phoenix1331/laravel-jev-hotdog`), titled **Hotdog Not Hotdog** (`APP_NAME`, used for the page title and header): drop a photo, get HOTDOG / NOT HOTDOG / NOT SURE, with a box drawn around the hot dog. Showcases Jev (TypeSafe) image input with Noul and Box question types. Learning repo, not a package: no Packagist, tags, releases or CHANGELOG; `main` is current.

## Stack

| Area | Choice |
|------|--------|
| Framework | Laravel 13, PHP 8.3+ (developed on 8.4); `composer.json` pins `config.platform.php` to 8.3.0 so the lock resolves Symfony 7.4 LTS rather than Symfony 8 (PHP 8.4+ only) |
| Runtime | Native WSL, `php artisan dev` locally; `php artisan serve` or Herd for users |
| Storage | None. Session `file`, cache `file`, queue `sync`, failed jobs `null`; only the `local` disk (Livewire temp) and `s3` remain in `config/filesystems.php` |
| Images | `intervention/image` 3.11; driver from `hotdog.image_driver` (`auto` = Imagick when loaded, else GD), built in `HotdogServiceProvider::imageManager()` |
| Frontend | Livewire 3.8 (`WithFileUploads`; brief pins v3 although v4 exists), Tailwind 4 via `@tailwindcss/vite`, Vite 8 |
| Quality | Pest 4 (+ Laravel, Livewire plugins), Larastan level 8 on `app/`, `config/`, `routes/` (`phpstan.neon`), arch tests in `tests/Unit/ArchTest.php` (no debug helpers, no `env()` outside config, strict types), Pint (`pint.json`: `laravel` preset + `declare_strict_types`, applied to every PHP file), Husky hooks, pcov for coverage (90% min on `app/`) |
| Git hooks | Husky 9 (`.husky/`, tracked): `commit-msg` enforces lowercase conventional commits, `pre-commit` runs Pint and `osv-scanner` on `composer.lock`; installed by `npm install` via `prepare` |
| CI | `.github/workflows/ci.yml`: `quality` (Pint, Larastan), `tests` (PHP 8.3 and 8.4 matrix, plus `hotdog:eval --min=100` in fake mode), `coverage` (pcov, `--min=90`, `shell: bash` so `tee` keeps pipefail); `JEV_FAKE=true`, no secrets |

## Deviations from the default 1331 stack

| Default | Here | Why |
|---------|------|-----|
| Docker + FrankenPHP | Native PHP | Clone-and-run in minutes with `composer install`; no containers to learn |
| MySQL | No database | Nothing is stored; `database/`, `User` model, `config/auth.php` and the `public` disk removed |
| Makefile | Composer scripts (`composer.json` `scripts`) | Brief forbids a Makefile; `composer` is already required |
| pcov in Dockerfile | pcov on host (`php8.4-pcov`) and in CI | No Dockerfile |

## Commands

**composer**: dev | serve | test | coverage (`--min=90`) | lint | lint:check | analyse

Composer 2.8+ required: the scripts use the `@no_additional_args` token (Laravel 13 skeleton default). `dev` and `serve` set `PHP_INI_SCAN_DIR=:$PWD/php.d` so the child PHP server loads `php.d/uploads.ini` (20M upload, 24M post); plain `php artisan serve` inherits the CLI default of 2M and phone photos fail.

## Configuration

| File | Keys |
|------|------|
| `config/jev.php` | `api_key`, `model` (`jev-preview`), `base_url`, `fake` (default `true`), `timeout` |
| `config/hotdog.php` | `unsure_min` / `unsure_max` (0.40 / 0.60), `max_upload_kb` (20480), `rate_limit_per_minute` (20), `samples_path` (`base_path('samples')`), `image_driver` (`auto`), `max_input_megapixels` (50) |

`env()` is only read in `config/`. `phpunit.xml` forces `JEV_FAKE=true` and an empty key.

## Design decisions

- **Noul decides, Box locates, one request** - Box always returns a box even when nothing is there, so the Noul answer decides the verdict and the box is shown only above `unsure_max`. Both questions go in one request (the pattern TypeSafe documents): the image is billed as input tokens on every request and output is free, so a second Box call would bill the image twice and add a round trip. Box edges are 0 to 1 fractions, so the overlay is plain CSS percentages
- **Not sure band** - confidence between `unsure_min` and `unsure_max` shows a third state; confidence always shown
- **Normalise before sending** - Jev accepts only still PNG/JPEG, 280 to 1568px per side (the docs say 280, not the 256 first assumed), 78,400 to 2,458,624 total pixels, max 16 MiB, and rejects rather than resizes. It also strips EXIF without applying orientation. Every upload is rotated upright, resized and re-encoded as baseline JPEG first
- **Preview API** - every image request sends `TypeSafe-Danger-Zone-Images: true`; images and Box need Jev 1.14 (`jev-preview` or `jev-1.14.0`; `jev-latest` is still 1.13, text only), so the model is pinned in config
- **One percentage on screen** - only the Noul probability (Confidence) is shown. The Box answer also has a confidence, but it measures how sure Jev is of the box edges, and users read any second percentage as a hot dog score. It stays on `BoundingBox::$confidence` for code and evals, not the UI
- **Show the normalised image** - the UI should display `NormalisedImage::$bytes`, not the original upload: padding for extreme aspect ratios shifts the frame, and the box fractions are relative to what Jev saw
- **Bring your own key** - no key in the repo, tests, fixtures or recordings; fake mode is the default so the UI works straight after cloning
- **Upload limits in three places** - PHP (`php.d/uploads.ini`, Herd users set it in Herd), Livewire temporary upload rule (`config/livewire.php`, from `HOTDOG_MAX_UPLOAD_KB`), component rule (`mimes` list, not `image`, because `image` excludes HEIC). Livewire validates the upload under `files.*` and reports PHP refusals via `validation.uploaded`, so friendly messages live in `lang/en/validation.php`. The UI shows the smaller of the app and PHP limits
- **Samples** - nine CC0 / public domain photos from Wikimedia Commons, chosen by licence metadata and checked by eye (no identifiable people), plus four edge cases generated by `samples/generate-edge-cases.php` (committed output). Credits and the wanted list live in `samples/CREDITS.md`; tests fail if a committed image is missing from `expected.json` or `CREDITS.md`, or does not normalise into Jev limits
- **Nothing stored** - uploads live in Livewire temp and are deleted after the result; logs hold only verdict, confidence and latency
- **No show branding** - no HBO name, logo, fonts or screenshots; one credit line in the README. The title Hotdog Not Hotdog was chosen by the owner
- **Design** - tokens in `resources/css/app.css` `@theme`: ink `#14213d`, relish `#1e7f45` (HOTDOG), ketchup `#c8102e` (NOT HOTDOG), mustard `#f2a900` (NOT SURE, drop zone border). Big Shoulders Display (a Chicago civic typeface, for the Chicago dog) for display, Instrument Sans for body, both from Bunny Fonts. Full-bleed verdict banner sized `clamp(3.5rem, 19vw, 13rem)` so NOT HOTDOG fits at 320px; one stamp animation, off under reduced motion. Every clickable element gets `cursor: pointer` via a base rule (Tailwind 4 preflight resets buttons to the default cursor)

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
| `HotdogDetector` contract | Built | `detect(NormalisedImage): HotdogResult`; `jev.fake` picks fake or Jev driver |
| `FakeHotdogDetector` | Built | `samples/expected.json` match by basename (tricky entries land nearer the band but stay correct), else a SHA-1 of the bytes drives confidence, box and latency; model `fake` |
| `App\Jev\JevClient` | Built | `systemOne()` and `models()` via `Http`: bearer key, images header on every call, two retries with backoff on 429/529 only, `ConnectionException` to timeout/unreachable, failures thrown as `JevException` |
| `App\Jev\JevError` / `JevException` | Built | Status-based error enum (400, 401/403, 413, 422, 429/529, timeout, unreachable, unexpected, missing key), each with an on-screen message that never includes the key |
| `JevHotdogDetector` | Built | One Noul (`is_hotdog`) + Box (`hotdog`) request with the image as a UUID-keyed asset; malformed box keeps the verdict and drops the box; logs `hotdog.detected` with verdict, confidence, latency, model, request id only |
| `ImageNormaliser` | Built | Intervention Image 3.11 (brief pins v3 although v4 exists). Accepts JPEG/PNG/WebP/GIF (first frame)/HEIC (Imagick only) by sniffed MIME; refuses inputs over `max_input_megapixels` from the header before decoding; auto-orients, strips metadata, flattens alpha on white; `targetSize()` scales to 280..1568 per side, padding with white when an extreme aspect ratio makes both limits impossible; baseline JPEG q85 |
| `ImageRejected` | Built | Friendly messages for unsupported format, HEIC without Imagick, damaged file, too many pixels |
| `App\Livewire\HotdogUploader` | Built | Full-page component at `/` (layout `resources/views/components/layouts/app.blade.php`). `updatedPhoto()` and `trySample($file)` share one pipeline: rate limit (per IP, `hotdog.rate_limit_per_minute`), validation, normalise, detect; temp upload deleted in `finally`. `#[Locked]` `$result` holds a data URI of the normalised image plus verdict, box CSS, model, latency; `$failure` holds friendly messages; unexpected errors are `report()`ed and shown generically. Every check dispatches `hotdog-checked` (in a `finally`), and the view scrolls to the top on it, and immediately on a sample click, so the loading state and verdict are always in view |
| `App\Hotdog\Samples\SampleCatalogue` / `Sample` | Built | Reads `samples/expected.json` (`file`, `expected`, optional `tricky`); names must match `SAFE_NAME` (no paths, safe in URLs and `wire:click`); `available()` filters to files on disk, so wanted samples can be listed before their photo exists. Singleton from `hotdog.samples_path`; used by the fake driver, thumbnails, `trySample()` and `hotdog:eval` |
| `SampleThumbnailController` | Built | `GET /samples/{file}/thumbnail` (`samples.thumbnail`): 480px JPEG via the shared `ImageManager` singleton, cached forever by name + mtime + size, 404 for anything unlisted, missing or undecodable |
| `hotdog:check` | Built | `App\Console\Commands\HotdogCheckCommand`: prints settings (never the key), then `GET /models` and one blank 280px PNG through the real `JevHotdogDetector` (Noul + Box, images header). Runs whenever a key is set, even in fake mode, so setup can be verified before switching; warns on text-only or unlisted models; exits 1 on any failure with the request id |
| `hotdog:eval` | Built | `App\Console\Commands\HotdogEvalCommand`: each available sample through the app's normaliser and `HotdogDetector` (so it honours `JEV_FAKE`); table plus overall, easy and tricky accuracy; NOT SURE counts as a fail and is tallied separately. `--only=easy|tricky`, `--min=` (exit 1 below it), `--force` (skip the credit prompt Jev mode shows). Per-sample errors are fails and the run continues; a missing or rejected key stops at once. CI runs it in fake mode with `--min=100` to prove every committed sample runs end to end |

## Host requirements

PHP extensions: `gd` (required), `exif` (orientation), `imagick` (optional; HEIC also needs ImageMagick built with libheif), `pcov` (coverage). Decoding needs about 4 bytes per pixel, so a 50 MP input needs roughly 200 MB of PHP memory. CI installs `gd, imagick, exif`, and the normaliser tests run against both drivers.
