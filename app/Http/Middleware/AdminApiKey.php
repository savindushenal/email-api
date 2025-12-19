<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiKey
{
    /**
     * Handle an incoming request.
     *
     * Admin API key is used for:
     * - Creating new domains
     * - Managing all domains
     * - Viewing all domain API keys
     * - System-wide operations
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Admin-Key') ?? $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Admin API key required',
                'hint' => 'Provide X-Admin-Key header',
            ], 401);
        }

        $adminKey = config('app.admin_api_key');

        if (!$adminKey) {
            return response()->json([
                'success' => false,
                'message' => 'Admin API key not configured on server',
                'hint' => 'Set ADMIN_API_KEY in .env file',
            ], 500);
        }

        if (!hash_equals($adminKey, $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid admin API key',
            ], 401);
        }

        return $next($request);
    }
}
