<?php

use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index']);

// A/B test versions:
// /?v=1 - Dark gradient (default)
// /?v=2 - Light/clean theme
// /?v=3 - Bold/energetic
// /?v=4 - Minimal/premium
