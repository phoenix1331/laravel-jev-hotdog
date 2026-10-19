<?php

declare(strict_types=1);

use App\Livewire\HotdogUploader;
use Illuminate\Support\Facades\Route;

Route::get('/', HotdogUploader::class);
