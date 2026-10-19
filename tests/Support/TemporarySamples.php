<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A throwaway samples directory, removed when the test process ends.
 */
final class TemporarySamples
{
    /**
     * @param  array<string, string>  $files  filename => contents
     */
    public static function make(?string $expectedJson = null, array $files = []): string
    {
        $dir = sys_get_temp_dir().'/hotdog-samples-'.bin2hex(random_bytes(6));
        mkdir($dir);

        if ($expectedJson !== null) {
            file_put_contents("{$dir}/expected.json", $expectedJson);
        }

        foreach ($files as $name => $contents) {
            file_put_contents("{$dir}/{$name}", $contents);
        }

        register_shutdown_function(static function () use ($dir): void {
            array_map('unlink', glob("{$dir}/*") ?: []);
            @rmdir($dir);
        });

        return $dir;
    }
}
