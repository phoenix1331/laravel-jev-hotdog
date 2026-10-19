<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\ImageNormaliser;
use App\Hotdog\ImageRejected;
use App\Hotdog\Samples\Sample;
use App\Hotdog\Samples\SampleCatalogue;
use App\Hotdog\Verdict;
use App\Jev\JevError;
use App\Jev\JevException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Scores the current driver against the golden set in samples/expected.json.
 * Samples go through the same normaliser and detector as the app. In fake
 * mode the fake reads the answers, so the score only proves the plumbing;
 * with JEV_FAKE=false it measures Jev.
 */
final class HotdogEvalCommand extends Command
{
    protected $signature = 'hotdog:eval
        {--only= : Only run one group: easy or tricky}
        {--min= : Exit with an error if overall accuracy (percent) is below this}
        {--force : Skip the API credit confirmation in Jev mode}';

    protected $description = 'Run the sample photos through the detector and report accuracy';

    private const GROUPS = ['easy', 'tricky'];

    /** Every request would fail the same way, so stop at the first one. */
    private const FATAL = [JevError::MissingKey, JevError::Unauthorised];

    public function handle(SampleCatalogue $catalogue, ImageNormaliser $normaliser, HotdogDetector $detector): int
    {
        $only = $this->option('only');
        $min = $this->option('min');

        if ($only !== null && ! in_array($only, self::GROUPS, true)) {
            $this->components->error('--only must be easy or tricky.');

            return self::INVALID;
        }

        if ($min !== null && (! is_numeric($min) || $min < 0 || $min > 100)) {
            $this->components->error('--min must be a percentage from 0 to 100.');

            return self::INVALID;
        }

        $samples = array_filter(
            $catalogue->available(),
            static fn (Sample $sample): bool => $only === null || self::group($sample) === $only,
        );

        $wanted = count($catalogue->all()) - count($catalogue->available());

        if ($samples === []) {
            $this->components->warn('No samples to run. Add photos listed in samples/expected.json.');

            return self::SUCCESS;
        }

        if (! $this->confirmCost(count($samples))) {
            $this->components->info('Cancelled. Nothing was sent.');

            return self::SUCCESS;
        }

        $rows = [];
        /** @var array<string, int> $runs */
        $runs = array_fill_keys(self::GROUPS, 0);
        /** @var array<string, int> $passes */
        $passes = array_fill_keys(self::GROUPS, 0);
        $unsure = 0;
        $models = [];
        $started = hrtime(true);

        foreach ($samples as $sample) {
            $group = self::group($sample);
            $runs[$group]++;

            try {
                $result = $detector->detect($normaliser->normalise((string) file_get_contents($sample->path), $sample->file));
            } catch (JevException $e) {
                if (in_array($e->error, self::FATAL, true)) {
                    $this->components->error($e->getMessage());

                    return self::FAILURE;
                }

                $rows[] = [$sample->file, $group, $sample->expected->label(), '<fg=red>error</>', '', '<fg=red>FAIL</> '.$e->error->value];

                continue;
            } catch (ImageRejected $e) {
                $rows[] = [$sample->file, $group, $sample->expected->label(), '<fg=red>error</>', '', '<fg=red>FAIL</> image rejected'];

                continue;
            }

            $passed = $result->verdict === $sample->expected;
            $passes[$group] += $passed ? 1 : 0;
            $unsure += $result->verdict === Verdict::Unsure ? 1 : 0;
            $models[$result->model] = true;

            $rows[] = [
                $sample->file,
                $group,
                $sample->expected->label(),
                $result->verdict->label(),
                number_format($result->confidence * 100, 1).'%',
                $passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            ];
        }

        $this->table(['Sample', 'Group', 'Expected', 'Verdict', 'Confidence', 'Result'], $rows);

        $total = array_sum($runs);
        $passed = array_sum($passes);
        $accuracy = $passed / $total * 100;

        $this->components->twoColumnDetail('<options=bold>Overall</>', self::score($passed, $total));

        foreach (array_filter($runs) as $group => $groupRuns) {
            $this->components->twoColumnDetail(ucfirst($group), self::score($passes[$group], $groupRuns));
        }

        $this->components->twoColumnDetail('Not sure answers', (string) $unsure);
        $this->components->twoColumnDetail('Model', $models === [] ? '-' : implode(', ', array_keys($models)));
        $this->components->twoColumnDetail('Time', number_format((hrtime(true) - $started) / 1e9, 1).' s');

        if ($wanted > 0) {
            $this->components->twoColumnDetail('Listed but not added yet', "{$wanted} (see samples/CREDITS.md)");
        }

        $this->newLine();

        if (Config::boolean('jev.fake')) {
            $this->components->warn('Fake mode reads the answers from expected.json, so this score proves the plumbing, not the model. Set JEV_FAKE=false to evaluate Jev.');
        }

        if (is_numeric($min) && $accuracy < (float) $min) {
            $this->components->error(sprintf('Accuracy %.1f%% is below the --min of %s%%.', $accuracy, $min));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function confirmCost(int $count): bool
    {
        if (Config::boolean('jev.fake') || $this->option('force')) {
            return true;
        }

        $this->components->warn("Jev mode: this sends {$count} images to TypeSafe and uses API credit (image tokens are billed as input).");

        return $this->confirm('Run the eval?', true);
    }

    private static function group(Sample $sample): string
    {
        return $sample->tricky ? 'tricky' : 'easy';
    }

    private static function score(int $passed, int $total): string
    {
        return sprintf('%d / %d (%.1f%%)', $passed, $total, $passed / $total * 100);
    }
}
