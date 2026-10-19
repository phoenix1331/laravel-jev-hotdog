<?php

declare(strict_types=1);

use App\Http\Controllers\SampleThumbnailController;
use Illuminate\Support\Facades\Cache;
use Tests\Support\ImageFixtures;
use Tests\Support\TemporarySamples;

beforeEach(function () {
    config(['hotdog.image_driver' => 'gd', 'hotdog.samples_path' => TemporarySamples::make(json_encode([
        ['file' => 'big.jpg', 'expected' => 'hotdog'],
        ['file' => 'broken.jpg', 'expected' => 'hotdog'],
        ['file' => 'wanted.jpg', 'expected' => 'hotdog'],
    ]), [
        'big.jpg' => ImageFixtures::jpeg(3000, 2000),
        'broken.jpg' => 'not really a jpeg',
        'unlisted.jpg' => ImageFixtures::jpeg(300, 300),
    ])]);
});

it('serves a cached jpeg thumbnail no larger than 480px', function () {
    $response = $this->get('/samples/big.jpg/thumbnail');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'max-age=86400, public');

    $size = getimagesizefromstring($response->getContent());

    expect([$size[0], $size[1]])->toBe([SampleThumbnailController::SIZE, 320]);
});

it('caches by file name, modification time and size', function () {
    $path = config('hotdog.samples_path').'/big.jpg';
    $key = sprintf('sample-thumbnail:big.jpg:%d:%d', filemtime($path), filesize($path));

    expect(Cache::has($key))->toBeFalse();

    $this->get('/samples/big.jpg/thumbnail')->assertOk();

    expect(Cache::has($key))->toBeTrue();
});

it('is not found for unlisted, missing, undecodable or traversal names', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'unlisted' => ['/samples/unlisted.jpg/thumbnail'],
    'listed but missing' => ['/samples/wanted.jpg/thumbnail'],
    'not an image' => ['/samples/broken.jpg/thumbnail'],
    'traversal' => ['/samples/..%2F.env/thumbnail'],
    'expected json' => ['/samples/expected.json/thumbnail'],
]);
