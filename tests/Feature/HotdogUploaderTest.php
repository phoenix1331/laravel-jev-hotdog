<?php

declare(strict_types=1);

use App\Hotdog\BoundingBox;
use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\HotdogResult;
use App\Hotdog\NormalisedImage;
use App\Jev\JevError;
use App\Jev\JevException;
use App\Livewire\HotdogUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\ImageFixtures;

final class StubDetector implements HotdogDetector
{
    /** @var list<NormalisedImage> */
    public array $seen = [];

    public function __construct(private readonly Closure|float $answer) {}

    public function detect(NormalisedImage $image): HotdogResult
    {
        $this->seen[] = $image;

        if ($this->answer instanceof Closure) {
            return ($this->answer)($image);
        }

        return HotdogResult::decide(
            new ConfidenceBand(0.4, 0.6),
            $this->answer,
            new BoundingBox(top: 0.2, bottom: 0.66, left: 0.19, right: 0.68, confidence: 0.9),
            321,
            'jev-1.14.0',
        );
    }
}

function stubDetector(Closure|float $answer): StubDetector
{
    $detector = new StubDetector($answer);
    app()->instance(HotdogDetector::class, $detector);

    return $detector;
}

function photo(string $name = 'lunch.jpg', ?string $bytes = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes ?? ImageFixtures::jpeg(640, 480));
}

/** Files left in Livewire's temporary upload disk. */
function temporaryUploads(): array
{
    return Storage::disk('tmp-for-tests')->allFiles();
}

beforeEach(function () {
    config(['jev.fake' => true, 'hotdog.image_driver' => 'gd']);
});

it('renders the drop zone with the fake mode badge', function () {
    Livewire::test(HotdogUploader::class)
        ->assertSee('Drop a photo. Is it a hot dog?')
        ->assertSeeHtml('data-test="fake-badge"')
        ->assertSeeHtml('accept="image/*,.heic,.heif"');
});

it('hides the fake mode badge with a real key', function () {
    config(['jev.fake' => false]);

    Livewire::test(HotdogUploader::class)->assertDontSeeHtml('data-test="fake-badge"');
});

it('is the home page', function () {
    $this->get('/')->assertOk()->assertSeeLivewire(HotdogUploader::class);
});

it('checks a photo as soon as it is uploaded, using the fake driver', function () {
    Livewire::test(HotdogUploader::class)
        ->set('photo', photo())
        ->assertHasNoErrors()
        ->assertSet('failure', null)
        ->assertSeeHtml('data-test="verdict"')
        ->assertSee('fake')
        ->assertSeeHtml('src="data:image/jpeg;base64,');
});

it('shows each verdict with its confidence', function (float $confidence, string $label, string $percent) {
    stubDetector($confidence);

    Livewire::test(HotdogUploader::class)
        ->set('photo', photo())
        ->assertSee($label)
        ->assertSeeInOrder(['Confidence', $percent])
        ->assertSee('jev-1.14.0')
        ->assertSee('321 ms');
})->with([
    'hotdog' => [0.97, 'HOTDOG', '97.0%'],
    'not hotdog' => [0.03, 'NOT HOTDOG', '3.0%'],
    'not sure' => [0.5, 'NOT SURE', '50.0%'],
]);

it('treats both band edges as not sure and draws the box only above them', function (float $confidence, string $verdict, bool $box) {
    stubDetector($confidence);

    $component = Livewire::test(HotdogUploader::class)->set('photo', photo());

    expect($component->get('result')['verdict'])->toBe($verdict)
        ->and($component->get('result')['box'] !== null)->toBe($box);
})->with([
    'just below min' => [0.3999, 'not_hotdog', false],
    'exactly min' => [0.4, 'unsure', false],
    'exactly max' => [0.6, 'unsure', false],
    'just above max' => [0.6001, 'hotdog', true],
]);

it('draws the box as css percentages over the photo', function () {
    stubDetector(0.97);

    Livewire::test(HotdogUploader::class)
        ->set('photo', photo())
        ->assertSeeHtml('data-test="box"')
        ->assertSeeHtml('top: 20%; left: 19%; width: 49%; height: 46%;');
});

it('passes the original filename and the normalised image to the detector', function () {
    $detector = stubDetector(0.9);

    Livewire::test(HotdogUploader::class)->set('photo', photo('classic.jpg', ImageFixtures::jpeg(5000, 3000)));

    expect($detector->seen)->toHaveCount(1)
        ->and($detector->seen[0]->originalName)->toBe('classic.jpg')
        ->and([$detector->seen[0]->width, $detector->seen[0]->height])->toBe([1568, 941]);
});

it('rejects files that are not images before normalising', function (UploadedFile $file, string $message) {
    $detector = stubDetector(0.9);

    Livewire::test(HotdogUploader::class)
        ->set('photo', $file)
        ->assertHasErrors('photo')
        ->assertSee($message)
        ->assertSet('result', null);

    expect($detector->seen)->toBeEmpty()->and(temporaryUploads())->toBeEmpty();
})->with([
    'pdf' => fn () => [UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'), 'That file type is not supported.'],
    'too big' => fn () => [UploadedFile::fake()->create('huge.jpg', 20481, 'image/jpeg'), 'That photo is over 20480 KB.'],
]);

it('shows the normaliser message for an image it cannot read', function () {
    $detector = stubDetector(0.9);

    Livewire::test(HotdogUploader::class)
        ->set('photo', photo('broken.jpg', substr(ImageFixtures::jpeg(640, 480), 0, 300)))
        ->assertSet('failure', 'That image could not be read. It may be damaged or incomplete.')
        ->assertSeeHtml('role="alert"');

    expect($detector->seen)->toBeEmpty();
});

it('shows a friendly message when jev fails', function (JevError $error) {
    stubDetector(fn () => throw new JevException($error, 401));

    Livewire::test(HotdogUploader::class)
        ->set('photo', photo())
        ->assertSet('failure', $error->message())
        ->assertSee($error->message())
        ->assertSet('result', null);
})->with([JevError::Unauthorised, JevError::Busy, JevError::Timeout, JevError::MissingKey]);

it('reports unexpected errors but never shows them', function () {
    Exceptions::fake();
    stubDetector(fn () => throw new RuntimeException('secret stack detail'));

    Livewire::test(HotdogUploader::class)
        ->set('photo', photo())
        ->assertSet('failure', HotdogUploader::GENERIC_FAILURE)
        ->assertDontSee('secret stack detail');

    Exceptions::assertReported(RuntimeException::class);
});

it('rate limits checks per ip', function () {
    config(['hotdog.rate_limit_per_minute' => 2]);
    $detector = stubDetector(0.9);

    $component = Livewire::test(HotdogUploader::class);

    $component->set('photo', photo())->assertSet('failure', null);
    $component->set('photo', photo())->assertSet('failure', null);
    $component->set('photo', photo())->assertSee('That is a lot of photos. Try again in');

    expect($detector->seen)->toHaveCount(2)->and(temporaryUploads())->toBeEmpty();
});

it('deletes the temporary upload after every outcome', function (Closure|float $answer) {
    stubDetector($answer);

    Livewire::test(HotdogUploader::class)->set('photo', photo());

    expect(temporaryUploads())->toBeEmpty();
})->with([
    'success' => [0.9],
    'jev failure' => [fn () => fn () => throw new JevException(JevError::Busy)],
]);

it('starts over from a result', function () {
    stubDetector(0.97);

    Livewire::test(HotdogUploader::class)
        ->set('photo', photo())
        ->assertSee('HOTDOG')
        ->call('startOver')
        ->assertSet('result', null)
        ->assertSet('failure', null)
        ->assertSet('photo', null)
        ->assertSee('Drop a photo. Is it a hot dog?');
});

it('starts over from an error, clearing validation messages', function () {
    Livewire::test(HotdogUploader::class)
        ->set('photo', UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'))
        ->assertHasErrors('photo')
        ->call('startOver')
        ->assertHasNoErrors();
});

it('advertises the smaller of the app and php upload limits', function () {
    config(['hotdog.max_upload_kb' => 1024]);

    Livewire::test(HotdogUploader::class)->assertSee('up to 1 MB');
});

it('does not let the browser overwrite the result', function () {
    Livewire::test(HotdogUploader::class)->set('result', ['verdict' => 'hotdog']);
})->throws(CannotUpdateLockedPropertyException::class);

it('explains an upload php refused before livewire saw it', function () {
    Livewire::test(HotdogUploader::class)
        ->call('_uploadErrored', 'photo', null, false)
        ->assertHasErrors('photo')
        ->assertSee('That photo did not finish uploading. It may be over the server upload limit.');
});

it('holds the upload on the temporary disk only while checking it', function () {
    $during = null;
    stubDetector(function () use (&$during) {
        $during = temporaryUploads();

        return HotdogResult::decide(new ConfidenceBand(0.4, 0.6), 0.1, null, 1, 'jev-1.14.0');
    });

    Livewire::test(HotdogUploader::class)->set('photo', photo());

    expect($during)->toHaveCount(1)->and(temporaryUploads())->toBeEmpty();
});

it('is called hotdog not hotdog', function () {
    $this->get('/')
        ->assertSee('<title>Hotdog Not Hotdog</title>', escape: false)
        ->assertSeeInOrder(['Hotdog Not Hotdog', 'Drop a photo. Is it a hot dog?']);
});
