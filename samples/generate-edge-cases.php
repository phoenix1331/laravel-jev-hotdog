<?php

declare(strict_types=1);

/*
 * Generates the edge-case samples: images that are not hot dogs but push
 * the normaliser through each path (huge, tiny, animated, WebP).
 *
 *   php samples/generate-edge-cases.php [output-directory]
 *
 * Writes to samples/ by default. The output is committed; rerun only to
 * regenerate it. The HEIC edge case comes from a real phone, not from here.
 */

use Intervention\Image\ImageManager;

require __DIR__.'/../vendor/autoload.php';

$out = rtrim($argv[1] ?? __DIR__, '/');

if (! is_dir($out) && ! mkdir($out, 0755, true)) {
    fwrite(STDERR, "Cannot create {$out}\n");
    exit(1);
}

/**
 * A sky with a few flat circles: clearly not food, and compresses small.
 */
function scene(int $width, int $height): GdImage
{
    $image = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y++) {
        $shade = (int) (120 + 100 * $y / $height);
        imageline($image, 0, $y, $width, $y, (int) imagecolorallocate($image, 90, $shade, 220));
    }

    $sun = (int) imagecolorallocate($image, 255, 214, 90);
    imagefilledellipse($image, (int) ($width * 0.75), (int) ($height * 0.3), (int) ($width * 0.2), (int) ($width * 0.2), $sun);

    $hill = (int) imagecolorallocate($image, 70, 140, 80);
    imagefilledellipse($image, (int) ($width * 0.3), $height, (int) ($width * 1.2), (int) ($height * 0.7), $hill);

    return $image;
}

$written = [];

// Larger than a 12 MP phone photo: must be scaled down to 1568px.
imagejpeg(scene(5000, 3750), "{$out}/edge-huge.jpg", 85);
$written[] = 'edge-huge.jpg';

// Below Jev's 280px minimum: must be scaled up.
imagepng(scene(100, 100), "{$out}/edge-tiny.png");
$written[] = 'edge-tiny.png';

// WebP is not accepted by Jev: must be converted.
imagewebp(scene(900, 600), "{$out}/edge-webp.webp", 80);
$written[] = 'edge-webp.webp';

// Animated: only the first frame may be sent.
$manager = ImageManager::gd();
$gif = $manager->animate(function ($animation) use ($manager): void {
    foreach ([[90, 160, 220], [220, 120, 90], [120, 200, 120]] as [$r, $g, $b]) {
        $animation->add($manager->create(400, 300)->fill(sprintf('%02x%02x%02x', $r, $g, $b)), 0.4);
    }
})->toGif();
file_put_contents("{$out}/edge-animated.gif", (string) $gif);
$written[] = 'edge-animated.gif';

foreach ($written as $file) {
    $size = getimagesize("{$out}/{$file}");
    printf("%-18s %5dx%-5d %s\n", $file, $size[0] ?? 0, $size[1] ?? 0, $size['mime'] ?? '?');
}
