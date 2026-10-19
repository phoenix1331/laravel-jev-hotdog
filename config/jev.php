<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Jev API
    |--------------------------------------------------------------------------
    |
    | Bring your own key from typesafe.ai. Image input is a preview feature,
    | so the model is pinned here: Box questions need jev-1.14.0 or
    | jev-preview. Fake mode answers locally and never calls the API.
    |
    */

    'api_key' => env('JEV_API_KEY'),

    'model' => env('JEV_MODEL', 'jev-preview'),

    'base_url' => env('JEV_BASE_URL', 'https://api.typesafe.ai/v1'),

    'fake' => (bool) env('JEV_FAKE', true),

    'timeout' => (int) env('JEV_TIMEOUT', 30),

];
