<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hotdog\Detectors\JevHotdogDetector;
use App\Hotdog\NormalisedImage;
use App\Jev\JevClient;
use App\Jev\JevError;
use App\Jev\JevException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Intervention\Image\ImageManager;

/**
 * Proves the Jev setup works before the app relies on it: the key, the
 * model, and one tiny image request with the preview header. Runs against
 * Jev whenever a key is set, even in fake mode, so you can check first and
 * switch JEV_FAKE afterwards.
 */
final class HotdogCheckCommand extends Command
{
    protected $signature = 'hotdog:check';

    protected $description = 'Check the Jev key, model and image support with one tiny request';

    /** Box needs Jev 1.14; jev-latest still points at text-only 1.13. */
    private const IMAGE_MODELS = ['jev-preview', 'jev-1.14.0'];

    public function handle(JevClient $client, JevHotdogDetector $detector, ImageManager $images): int
    {
        $fake = Config::boolean('jev.fake');
        $model = Config::string('jev.model');
        $hasKey = trim((string) Config::get('jev.api_key')) !== '';

        $this->newLine();
        $this->components->twoColumnDetail('Fake mode', $fake ? '<fg=yellow>on</> (answers are made up locally)' : '<fg=green>off</> (the app asks Jev)');
        $this->components->twoColumnDetail('API key', $hasKey ? '<fg=green>set</>' : '<fg=red>not set</>');
        $this->components->twoColumnDetail('Model', $model);
        $this->components->twoColumnDetail('Base URL', Config::string('jev.base_url'));
        $this->newLine();

        if (! $hasKey) {
            if ($fake) {
                $this->components->info('No key, so nothing to check. Fake mode works without one. Add JEV_API_KEY to .env to use Jev.');

                return self::SUCCESS;
            }

            $this->components->error(JevError::MissingKey->message());

            return self::FAILURE;
        }

        if (! in_array($model, self::IMAGE_MODELS, true)) {
            $this->components->warn("{$model} may not support images or Box questions. Use jev-preview or jev-1.14.0.");
        }

        try {
            $models = $client->models();
        } catch (JevException $e) {
            return $this->reportFailure('Key', $e);
        }

        $this->components->twoColumnDetail('Key accepted', '<fg=green>OK</>');
        $this->components->twoColumnDetail(
            'Models on this key',
            $models === [] ? '<fg=yellow>none listed</>' : implode(', ', $models),
        );

        // Versioned IDs work even though the list only shows aliases.
        if ($models !== [] && ! in_array($model, $models, true) && preg_match('/^jev-\d+\.\d+\.\d+$/', $model) !== 1) {
            $this->components->warn("{$model} is not in the list for this key.");
        }

        $png = (string) $images->create(280, 280)->fill('ffffff')->toPng();

        try {
            $result = $detector->detect(new NormalisedImage($png, 'image/png', 280, 280, 280, 280, 'hotdog-check.png'));
        } catch (JevException $e) {
            return $this->reportFailure('Image request', $e);
        }

        $this->components->twoColumnDetail('Image + Box request', "<fg=green>OK</> answered by {$result->model} in {$result->latencyMs} ms");
        $this->newLine();

        $this->components->info($fake
            ? 'Jev is ready. Set JEV_FAKE=false in .env to use it in the app.'
            : 'Jev is ready, and the app is using it.');

        return self::SUCCESS;
    }

    private function reportFailure(string $step, JevException $e): int
    {
        $this->components->twoColumnDetail($step, '<fg=red>FAILED</>');
        $this->newLine();
        $this->components->error($e->getMessage());

        if ($e->error === JevError::ImageRejected) {
            $this->line('  A blank 280px PNG was rejected, so the request itself is the problem: check JEV_MODEL is jev-preview or jev-1.14.0.');
        }

        if ($e->requestId !== null) {
            $this->line("  Request id: {$e->requestId}");
        }

        $this->newLine();

        return self::FAILURE;
    }
}
