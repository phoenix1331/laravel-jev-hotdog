<?php

declare(strict_types=1);

namespace App\Providers;

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\Detectors\FakeHotdogDetector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use LogicException;

class HotdogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfidenceBand::class, fn (): ConfidenceBand => new ConfidenceBand(
            min: Config::float('hotdog.unsure_min'),
            max: Config::float('hotdog.unsure_max'),
        ));

        $this->app->when(FakeHotdogDetector::class)
            ->needs('$expectedPath')
            ->give(fn (): string => Config::string('hotdog.samples_path').'/expected.json');

        $this->app->bind(HotdogDetector::class, function (Application $app): HotdogDetector {
            if (! Config::boolean('jev.fake')) {
                throw new LogicException('Only the fake driver is available in this build. Set JEV_FAKE=true.');
            }

            return $app->make(FakeHotdogDetector::class);
        });
    }
}
