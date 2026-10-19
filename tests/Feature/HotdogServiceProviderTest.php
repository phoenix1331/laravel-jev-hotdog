<?php

declare(strict_types=1);

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\Detectors\FakeHotdogDetector;
use App\Hotdog\NormalisedImage;
use App\Hotdog\Verdict;

it('binds the fake detector when fake mode is on', function () {
    config(['jev.fake' => true]);

    expect(app(HotdogDetector::class))->toBeInstanceOf(FakeHotdogDetector::class);
});

it('refuses to resolve a detector when fake mode is off', function () {
    config(['jev.fake' => false]);

    app(HotdogDetector::class);
})->throws(LogicException::class, 'JEV_FAKE=true');

it('builds the not sure band from config', function () {
    config(['hotdog.unsure_min' => 0.3, 'hotdog.unsure_max' => 0.7]);

    $band = app(ConfidenceBand::class);

    expect($band->min)->toBe(0.3)->and($band->max)->toBe(0.7);
});

it('reads expectations from the configured samples path', function () {
    $dir = sys_get_temp_dir().'/hotdog-samples-'.bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents("{$dir}/expected.json", json_encode([['file' => 'classic.jpg', 'expected' => 'not_hotdog']]));
    config(['jev.fake' => true, 'hotdog.samples_path' => $dir]);

    $result = app(HotdogDetector::class)->detect(
        new NormalisedImage('bytes', 'image/jpeg', 300, 300, 300, 300, 'classic.jpg')
    );

    expect($result->verdict)->toBe(Verdict::NotHotdog);

    unlink("{$dir}/expected.json");
    rmdir($dir);
});
