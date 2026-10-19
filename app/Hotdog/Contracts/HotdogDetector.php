<?php

declare(strict_types=1);

namespace App\Hotdog\Contracts;

use App\Hotdog\HotdogResult;
use App\Hotdog\NormalisedImage;

interface HotdogDetector
{
    public function detect(NormalisedImage $image): HotdogResult;
}
