<?php

declare(strict_types=1);

namespace App\Hotdog\Samples;

use App\Hotdog\Verdict;

/**
 * The golden set in samples/expected.json. Entries may be listed before
 * their photo is added; `available()` only returns files on disk. Lookups
 * go through the listed names, so nothing outside the list can be read.
 */
final class SampleCatalogue
{
    /** Plain filenames only: no paths, and safe inside URLs and wire:click. */
    public const SAFE_NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /** @var array<string, Sample>|null */
    private ?array $samples = null;

    public function __construct(
        private readonly string $directory,
    ) {}

    /**
     * Every listed sample, in expected.json order.
     *
     * @return array<string, Sample>
     */
    public function all(): array
    {
        return $this->samples ??= $this->load();
    }

    /**
     * @return array<string, Sample>
     */
    public function available(): array
    {
        return array_filter($this->all(), static fn (Sample $sample): bool => $sample->exists());
    }

    public function find(string $file): ?Sample
    {
        return $this->all()[basename($file)] ?? null;
    }

    /**
     * @return array<string, Sample>
     */
    private function load(): array
    {
        $path = $this->directory.'/expected.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $samples = [];

        foreach (is_array($decoded) ? $decoded : [] as $entry) {
            $file = is_array($entry) ? ($entry['file'] ?? null) : null;
            $expected = is_array($entry) && is_string($entry['expected'] ?? null) ? Verdict::tryFrom($entry['expected']) : null;

            if (! is_string($file) || preg_match(self::SAFE_NAME, $file) !== 1 || $expected === null) {
                continue;
            }

            $samples[$file] = new Sample(
                file: $file,
                expected: $expected,
                tricky: ($entry['tricky'] ?? false) === true,
                path: $this->directory.'/'.$file,
            );
        }

        return $samples;
    }
}
