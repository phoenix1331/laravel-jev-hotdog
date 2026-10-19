<?php

declare(strict_types=1);

use App\Hotdog\Samples\Sample;
use App\Hotdog\Samples\SampleCatalogue;
use App\Hotdog\Verdict;
use Tests\Support\TemporarySamples;

function catalogue(array $entries, array $files = []): SampleCatalogue
{
    return new SampleCatalogue(TemporarySamples::make(json_encode($entries), $files));
}

it('reads entries in order with their expected verdict and tricky flag', function () {
    $samples = catalogue([
        ['file' => 'classic.jpg', 'expected' => 'hotdog'],
        ['file' => 'corn-dog.jpg', 'expected' => 'not_hotdog', 'tricky' => true],
    ])->all();

    expect(array_keys($samples))->toBe(['classic.jpg', 'corn-dog.jpg'])
        ->and($samples['classic.jpg']->expected)->toBe(Verdict::Hotdog)
        ->and($samples['classic.jpg']->tricky)->toBeFalse()
        ->and($samples['corn-dog.jpg']->expected)->toBe(Verdict::NotHotdog)
        ->and($samples['corn-dog.jpg']->tricky)->toBeTrue();
});

it('skips malformed entries and anything that is not a plain filename', function () {
    $samples = catalogue([
        ['file' => 'ok.jpg', 'expected' => 'hotdog'],
        ['file' => '../.env', 'expected' => 'hotdog'],
        ['file' => 'nested/photo.jpg', 'expected' => 'hotdog'],
        ['file' => "it's.jpg", 'expected' => 'hotdog'],
        ['file' => '.hidden.jpg', 'expected' => 'hotdog'],
        ['file' => 'with space.jpg', 'expected' => 'hotdog'],
        ['file' => 'unknown.jpg', 'expected' => 'sausage'],
        ['file' => 42, 'expected' => 'hotdog'],
        ['expected' => 'hotdog'],
        ['file' => 'truthy.jpg', 'expected' => 'hotdog', 'tricky' => 'yes'],
        'not an entry',
    ])->all();

    expect(array_keys($samples))->toBe(['ok.jpg', 'truthy.jpg'])
        ->and($samples['truthy.jpg']->tricky)->toBeFalse();
});

it('lists only samples whose file exists as available', function () {
    $catalogue = catalogue(
        [['file' => 'here.jpg', 'expected' => 'hotdog'], ['file' => 'wanted.jpg', 'expected' => 'hotdog']],
        ['here.jpg' => 'bytes'],
    );

    expect(array_keys($catalogue->available()))->toBe(['here.jpg'])
        ->and($catalogue->find('wanted.jpg')?->exists())->toBeFalse();
});

it('finds by basename and never outside the list', function () {
    $catalogue = catalogue([['file' => 'classic.jpg', 'expected' => 'hotdog']], ['classic.jpg' => 'x', 'secret.jpg' => 'x']);

    expect($catalogue->find('classic.jpg'))->toBeInstanceOf(Sample::class)
        ->and($catalogue->find('/tmp/upload/classic.jpg')?->file)->toBe('classic.jpg')
        ->and($catalogue->find('secret.jpg'))->toBeNull()
        ->and($catalogue->find('../expected.json'))->toBeNull();
});

it('treats a missing or broken expected.json as an empty set', function (?string $json) {
    expect((new SampleCatalogue(TemporarySamples::make($json)))->all())->toBe([]);
})->with(['missing' => [null], 'malformed' => ['{nope'], 'not a list' => ['"classic.jpg"']]);

it('labels samples for people', function (string $file, string $label) {
    expect((new Sample($file, Verdict::Hotdog, false, "/x/{$file}"))->label())->toBe($label);
})->with([
    ['half-eaten.jpg', 'Half eaten'],
    ['edge_tiny.png', 'Edge tiny'],
    ['classic.jpg', 'Classic'],
]);
