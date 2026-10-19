<?php

declare(strict_types=1);

namespace App\Providers;

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\Detectors\FakeHotdogDetector;
use App\Hotdog\Detectors\JevHotdogDetector;
use App\Hotdog\ImageNormaliser;
use App\Jev\JevClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

class HotdogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfidenceBand::class, fn (): ConfidenceBand => new ConfidenceBand(
            min: Config::float('hotdog.unsure_min'),
            max: Config::float('hotdog.unsure_max'),
        ));

        $this->app->bind(JevClient::class, fn (): JevClient => new JevClient(
            apiKey: is_string($key = Config::get('jev.api_key')) ? $key : null,
            baseUrl: Config::string('jev.base_url'),
            timeoutSeconds: Config::integer('jev.timeout'),
        ));

        $this->app->bind(ImageNormaliser::class, fn (): ImageNormaliser => new ImageNormaliser(
            manager: self::imageManager(Config::string('hotdog.image_driver')),
            maxInputMegapixels: Config::integer('hotdog.max_input_megapixels'),
        ));

        $this->app->when(FakeHotdogDetector::class)
            ->needs('$expectedPath')
            ->give(fn (): string => Config::string('hotdog.samples_path').'/expected.json');

        $this->app->when(JevHotdogDetector::class)
            ->needs('$model')
            ->give(fn (): string => Config::string('jev.model'));

        $this->app->bind(HotdogDetector::class, fn (Application $app): HotdogDetector => Config::boolean('jev.fake')
            ? $app->make(FakeHotdogDetector::class)
            : $app->make(JevHotdogDetector::class));
    }

    /**
     * Auto-orient on read, strip metadata on encode, and decode only the
     * first frame of an animation.
     */
    public static function imageManager(string $driver): ImageManager
    {
        $options = ['autoOrientation' => true, 'decodeAnimation' => false, 'strip' => true];

        return match ($driver) {
            'auto' => extension_loaded('imagick') ? ImageManager::imagick(...$options) : ImageManager::gd(...$options),
            'gd' => ImageManager::gd(...$options),
            'imagick' => ImageManager::imagick(...$options),
            default => throw new InvalidArgumentException("HOTDOG_IMAGE_DRIVER must be auto, gd or imagick, got {$driver}."),
        };
    }
}
