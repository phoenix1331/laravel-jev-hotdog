<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\HotdogResult;
use App\Hotdog\ImageNormaliser;
use App\Hotdog\ImageRejected;
use App\Hotdog\NormalisedImage;
use App\Jev\JevException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * The whole app: drop a photo, get a verdict. Nothing is stored; the
 * temporary upload is deleted as soon as the photo has been checked.
 */
final class HotdogUploader extends Component
{
    use WithFileUploads;

    /** HEIC is not in Laravel's `image` rule, so the accepted list is explicit. */
    public const ACCEPTED_EXTENSIONS = 'jpg,jpeg,png,webp,gif,heic,heif';

    public const GENERIC_FAILURE = 'Something went wrong checking that photo. Try again.';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    /**
     * @var array{verdict: string, label: string, confidence: float, box: array{top: string, left: string, width: string, height: string}|null, boxConfidence: float|null, latencyMs: int, model: string, image: string, width: int, height: int}|null
     */
    #[Locked]
    public ?array $result = null;

    #[Locked]
    public ?string $failure = null;

    public function updatedPhoto(): void
    {
        $this->reset('result', 'failure');

        try {
            $this->check();
        } finally {
            $this->discardUpload();
        }
    }

    public function startOver(): void
    {
        $this->discardUpload();
        $this->reset('result', 'failure');
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.hotdog-uploader', [
            'fakeMode' => Config::boolean('jev.fake'),
            'maxUploadMb' => $this->maxUploadMb(),
            'accept' => 'image/*,.heic,.heif',
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:'.Config::integer('hotdog.max_upload_kb'), 'mimes:'.self::ACCEPTED_EXTENSIONS],
        ];
    }

    private function check(): void
    {
        if (! $this->withinRateLimit()) {
            return;
        }

        $this->validate();

        try {
            /** @var TemporaryUploadedFile $photo */
            $photo = $this->photo;

            $image = app(ImageNormaliser::class)->normalise((string) $photo->get(), $photo->getClientOriginalName());

            $this->result = self::present(app(HotdogDetector::class)->detect($image), $image);
        } catch (ImageRejected|JevException $e) {
            $this->failure = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $this->failure = self::GENERIC_FAILURE;
        }
    }

    private function withinRateLimit(): bool
    {
        $key = 'hotdog-upload:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, Config::integer('hotdog.rate_limit_per_minute'))) {
            $this->failure = 'That is a lot of photos. Try again in '.RateLimiter::availableIn($key).' seconds.';

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    private function discardUpload(): void
    {
        if ($this->photo instanceof TemporaryUploadedFile && $this->photo->exists()) {
            $this->photo->delete();
        }

        $this->photo = null;
    }

    /**
     * The preview is the normalised image Jev saw, so the box fractions line
     * up even when the normaliser padded an extreme aspect ratio.
     *
     * @return array{verdict: string, label: string, confidence: float, box: array{top: string, left: string, width: string, height: string}|null, boxConfidence: float|null, latencyMs: int, model: string, image: string, width: int, height: int}
     */
    private static function present(HotdogResult $result, NormalisedImage $image): array
    {
        return [
            'verdict' => $result->verdict->value,
            'label' => $result->verdict->label(),
            'confidence' => $result->confidence,
            'box' => $result->box?->toCssPercentages(),
            'boxConfidence' => $result->box?->confidence,
            'latencyMs' => $result->latencyMs,
            'model' => $result->model,
            'image' => 'data:'.$image->mime.';base64,'.$image->base64(),
            'width' => $image->width,
            'height' => $image->height,
        ];
    }

    /**
     * The smaller of the app limit and PHP's own upload limits, which reject
     * the file before Livewire sees it.
     */
    private function maxUploadMb(): int
    {
        $appLimit = Config::integer('hotdog.max_upload_kb') * 1024;

        return max(1, (int) floor(min($appLimit, UploadedFile::getMaxFilesize()) / 1024 / 1024));
    }
}
