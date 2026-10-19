<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Not sure band
    |--------------------------------------------------------------------------
    |
    | Confidence between these bounds shows NOT SURE instead of a verdict.
    | Above the max, a Box question locates the hot dog.
    |
    */

    'unsure_min' => (float) env('HOTDOG_UNSURE_MIN', 0.40),

    'unsure_max' => (float) env('HOTDOG_UNSURE_MAX', 0.60),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'max_upload_kb' => (int) env('HOTDOG_MAX_UPLOAD_KB', 20480),

    'rate_limit_per_minute' => (int) env('HOTDOG_RATE_LIMIT', 20),

    /*
    |--------------------------------------------------------------------------
    | Image normaliser
    |--------------------------------------------------------------------------
    |
    | Driver: auto (Imagick when the extension is loaded, else GD), gd or
    | imagick. HEIC needs Imagick built with HEIC support. Inputs above the
    | megapixel limit are refused before decoding to protect memory.
    |
    */

    'image_driver' => env('HOTDOG_IMAGE_DRIVER', 'auto'),

    'max_input_megapixels' => (int) env('HOTDOG_MAX_INPUT_MEGAPIXELS', 50),

    /*
    |--------------------------------------------------------------------------
    | Samples
    |--------------------------------------------------------------------------
    |
    | Golden-set photos and expected.json, used by the fake driver and
    | by hotdog:eval.
    |
    */

    'samples_path' => base_path('samples'),

];
