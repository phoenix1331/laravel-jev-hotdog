<?php

declare(strict_types=1);

namespace App\Providers;

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\Detectors\FakeHotdogDetector;
use App\Hotdog\Detectors\JevHotdogDetector;
use App\Jev\JevClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

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
}
