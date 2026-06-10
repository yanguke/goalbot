<?php

use App\Http\Controllers\Webhook\WhatsAppController;
use App\Http\Controllers\Webhook\WhatsAppInteractiveController;
use App\Http\Controllers\Webhook\WhatsAppVerifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// WhatsApp Webhook Verification (GET - for Facebook to verify)
Route::get('/webhook/whatsapp', [WhatsAppVerifyController::class, 'verify']);

// WhatsApp Webhook Receiving (POST - for incoming messages and button clicks)
Route::post('/webhook/whatsapp', [WhatsAppInteractiveController::class, 'handle']);

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'service' => 'GoalBot']);
});
