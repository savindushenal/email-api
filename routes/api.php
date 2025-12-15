<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\TemplateController;

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
    
    // Template management
    Route::prefix('email/templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index']);                    // List all templates
        Route::post('/', [TemplateController::class, 'store']);                   // Create new template
        Route::get('/{templateKey}', [TemplateController::class, 'show']);        // Get single template
        Route::put('/{templateKey}', [TemplateController::class, 'update']);      // Update template
        Route::delete('/{templateKey}', [TemplateController::class, 'destroy']);  // Delete template
        Route::post('/{templateKey}/preview', [TemplateController::class, 'preview']); // Preview template
    });
});
