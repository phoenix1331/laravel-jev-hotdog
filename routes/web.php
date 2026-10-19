<?php

declare(strict_types=1);

use App\Http\Controllers\SampleThumbnailController;
use App\Livewire\HotdogUploader;
use Illuminate\Support\Facades\Route;

Route::get('/', HotdogUploader::class);

Route::get('/samples/{file}/thumbnail', SampleThumbnailController::class)
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('samples.thumbnail');
