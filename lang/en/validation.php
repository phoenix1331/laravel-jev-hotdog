<?php

declare(strict_types=1);

/*
 * Merged over the framework's validation messages. Livewire validates the
 * upload itself under `files.*`, and reports uploads PHP refused (over
 * upload_max_filesize) through `uploaded`, so the friendly wording has to
 * live here rather than in the component.
 */
return [
    'uploaded' => 'That :attribute did not finish uploading. It may be over the server upload limit.',

    'custom' => [
        'photo' => [
            'mimes' => 'That file type is not supported. Try a JPEG, PNG, WebP, GIF or HEIC photo.',
            'max' => 'That photo is over :max KB. Try a smaller one.',
        ],
        'files.*' => [
            'max' => 'That photo is over :max KB. Try a smaller one.',
        ],
    ],
];
