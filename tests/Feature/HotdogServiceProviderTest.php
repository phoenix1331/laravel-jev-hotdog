<?php

declare(strict_types=1);

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\Detectors\FakeHotdogDetector;
use App\Hotdog\Detectors\JevHotdogDetector;
use App\Hotdog\NormalisedImage;
use App\Hotdog\Verdict;
use App\Jev\JevError;
use App\Jev\JevException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('binds the fake detector when fake mode is on', function () {
    config(['jev.fake' => true]);

    expect(app(HotdogDetector::class))->toBeInstanceOf(FakeHotdogDetector::class);
});

it('binds the jev detector when fake mode is off', function () {
    config(['jev.fake' => false]);

    expect(app(HotdogDetector::class))->toBeInstanceOf(JevHotdogDetector::class);
});

it('builds the jev client from config and fails friendly without a key', function (mixed $key) {
    config(['jev.fake' => false, 'jev.api_key' => $key]);
    Http::fake();

    expect(fn () => app(HotdogDetector::class)->detect(new NormalisedImage('b', 'image/jpeg', 300, 300, 300, 300)))
        ->toThrow(JevException::class, JevError::MissingKey->message());

    Http::assertNothingSent();
})->with(['null' => [null], 'empty' => [''], 'not a string' => [true]]);

it('sends the configured model to the configured base url', function () {
    config(['jev.fake' => false, 'jev.api_key' => 'k', 'jev.model' => 'jev-1.14.0', 'jev.base_url' => 'https://jev.example/v1']);
    Http::fake(['https://jev.example/v1/systemone' => Http::response(['model' => 'jev-1.14.0', 'answers' => ['is_hotdog' => ['type' => 'noul', 'noul' => 0.1]]])]);

    app(HotdogDetector::class)->detect(new NormalisedImage('b', 'image/jpeg', 300, 300, 300, 300));

    Http::assertSent(fn (Request $request) => $request['model'] === 'jev-1.14.0');
});

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
