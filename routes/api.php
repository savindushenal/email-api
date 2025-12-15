<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmailController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public health check
Route::get('/health', [EmailController::class, 'health']);

// Protected routes (require API key authentication)
Route::middleware(['api.key', 'throttle:60,1'])->group(function () {
    // Send email
    Route::post('/email/send', [EmailController::class, 'send']);
    
    // Get statistics
    Route::get('/email/stats', [EmailController::class, 'stats']);
});
