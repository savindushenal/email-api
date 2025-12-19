<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\DomainController;

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

// ============================================
// ADMIN ROUTES (Requires X-Admin-Key header)
// ============================================
Route::middleware(['admin.key'])->prefix('admin')->group(function () {
    // Domain Management
    Route::prefix('domains')->group(function () {
        Route::get('/', [DomainController::class, 'index']);                          // List all domains
        Route::post('/', [DomainController::class, 'store']);                         // Create new domain
        Route::get('/{domain}', [DomainController::class, 'show']);                   // Get domain details
        Route::put('/{domain}', [DomainController::class, 'update']);                 // Update domain config
        Route::delete('/{domain}', [DomainController::class, 'destroy']);             // Delete domain
        Route::post('/{domain}/regenerate-key', [DomainController::class, 'regenerateApiKey']); // Regenerate API key
        Route::post('/{domain}/test-email', [DomainController::class, 'testEmail']); // Test email config
        Route::get('/{domain}/api-key', [DomainController::class, 'getApiKey']);     // Get domain API key (recovery)
    });
});

// ============================================
// DOMAIN ROUTES (Requires X-API-Key header)
// ============================================
Route::middleware(['api.key', 'throttle:60,1'])->group(function () {
    // Send email
    Route::post('/email/send', [EmailController::class, 'send']);
    
    // Get statistics
    Route::get('/email/stats', [EmailController::class, 'stats']);
    
    // Template management (scoped to authenticated domain)
    Route::prefix('email/templates')->group(function () {
        Route::get('/', [TemplateController::class, 'index']);                    // List all templates
        Route::post('/', [TemplateController::class, 'store']);                   // Create new template
        Route::get('/{templateKey}', [TemplateController::class, 'show']);        // Get single template
        Route::put('/{templateKey}', [TemplateController::class, 'update']);      // Update template
        Route::delete('/{templateKey}', [TemplateController::class, 'destroy']);  // Delete template
        Route::post('/{templateKey}/preview', [TemplateController::class, 'preview']); // Preview template
    });
});
