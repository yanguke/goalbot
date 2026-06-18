<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminSubscriberController;
use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index']);
Route::get('/go', [LandingPageController::class, 'click']);
Route::get('/admin/analytics', [AdminAnalyticsController::class, 'index']);
Route::get('/admin/subscribers', [AdminSubscriberController::class, 'index']);
Route::post('/admin/subscribers/{id}', [AdminSubscriberController::class, 'update']);
Route::post('/admin/broadcast', [AdminSubscriberController::class, 'broadcast']);

// A/B test versions:
// /?v=1 - Dark gradient (default)
// /?v=2 - Light/clean theme
// /?v=3 - Bold/energetic
// /?v=4 - Minimal/premium
