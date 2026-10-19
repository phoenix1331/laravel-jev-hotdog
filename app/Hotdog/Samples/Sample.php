<?php

declare(strict_types=1);

namespace App\Hotdog\Samples;

use App\Hotdog\Verdict;

final readonly class Sample
{
    public function __construct(
        public string $file,
        public Verdict $expected,
        public bool $tricky,
        public string $path,
    ) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * "half-eaten.jpg" becomes "Half eaten", for alt text and labels.
     */
    public function label(): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', pathinfo($this->file, PATHINFO_FILENAME)));
    }
}
