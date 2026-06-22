<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminSubscriberController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LeadCaptureController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index']);
Route::get('/go', [LandingPageController::class, 'click']);
Route::post('/lead-capture', [LeadCaptureController::class, 'store'])->name('lead.capture');

// Admin authentication
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.attempt');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Protected admin area (session login OR ?key= fallback)
Route::middleware('admin')->group(function () {
    Route::get('/admin/analytics', [AdminAnalyticsController::class, 'index'])->name('admin.analytics');
    Route::get('/admin/subscribers', [AdminSubscriberController::class, 'index'])->name('admin.subscribers');
    Route::post('/admin/subscribers/{id}', [AdminSubscriberController::class, 'update']);
    Route::post('/admin/broadcast', [AdminSubscriberController::class, 'broadcast']);
});

// A/B test versions:
// /?v=1 - Dark gradient (default)
// /?v=2 - Light/clean theme
// /?v=3 - Bold/energetic
// /?v=4 - Minimal/premium
